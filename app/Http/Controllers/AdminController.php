<?php

namespace App\Http\Controllers;

use App\Models\DigitalReceipt;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use App\Services\LockCanteenAccount;
use App\Services\SendCanteenPasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function __construct(private LockCanteenAccount $access) {}

    public function users(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:customer,staff,admin'],
            'active' => ['nullable', 'boolean'],
            'edit' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = $filters['search'] ?? '';
        $creatingAccount = $request->old('creating_account') || $request->boolean('create');
        $editingId = $request->old('editing_account', $filters['edit'] ?? null);
        $users = User::with('stall')->when($search !== '', function ($query) use ($search): void {
            $query->where(fn ($accounts) => $accounts->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"));
        })->when(isset($filters['role']), fn ($query) => $query->where('user_type', $filters['role']))
            ->when(isset($filters['active']), fn ($query) => $query->where('is_active', $filters['active']));

        return view('admin.users', [
            'users' => $users->orderBy('name')->orderBy('id')->paginate(12)->appends($request->only(['search', 'role', 'active'])),
            'counts' => User::selectRaw('user_type, COUNT(*) AS count')->groupBy('user_type')->pluck('count', 'user_type'),
            'stalls' => Stall::orderBy('id')->get(),
            'editingAccount' => ! $creatingAccount && is_numeric($editingId) ? User::find((int) $editingId) : null,
            'creatingAccount' => $creatingAccount,
        ]);
    }

    public function create(): RedirectResponse
    {
        return to_route('admin.users.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['creating_account' => true]);
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'regex:/\A[^\x00]{1,72}\z/'],
            'contact_phone' => ['required', 'string', 'max:32', 'regex:/\A\+?[0-9][0-9 ()-]{5,29}[0-9]\z/'],
            'user_type' => ['required', 'in:customer,staff,admin'],
            'stall_id' => ['exclude_unless:user_type,staff', 'required', 'integer', 'exists:stalls,id'],
        ]);
        try {
            DB::transaction(function () use ($data, $request): void {
                $this->access->handle($request->user(), 'admin');
                $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                $user->contact_phone = $data['contact_phone'];
                $user->user_type = $data['user_type'];
                $user->stall_id = $data['stall_id'] ?? null;
                $user->save();
                $user->revokePasswordResetTokens();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email address is already in use.']);
        }

        return to_route('admin.users.index')->with('status', 'Account created.');
    }

    public function stalls(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:open,closed']]);
        $query = Stall::with('staff')->when($filters['search'] ?? null, function ($query, string $search): void {
            $query->where(function ($query) use ($search): void {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereHas('staff', fn ($staff) => $staff->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"));
                if (preg_match('/\A(?:ST-)?(\d+)\z/i', $search, $matches)) {
                    $query->orWhere('id', (int) $matches[1]);
                }
            });
        });
        $all = Stall::orderBy('id')->get();
        $stalls = $query->orderBy('id')->get()->filter(fn (Stall $stall) => ! isset($filters['status']) || $stall->isTakingOrders() === ($filters['status'] === 'open'));

        return view('admin.stalls', [
            'stalls' => $stalls,
            'totalStalls' => $all->count(),
            'openStalls' => $all->filter(fn (Stall $stall) => $stall->isTakingOrders())->count(),
            'deliveryStalls' => $all->where('delivery_enabled', true)->count(),
            'staffAccounts' => User::with('stall')->where('user_type', 'staff')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function createStall(Request $request): RedirectResponse
    {
        $request->merge(['stall_dialog' => 'register-stall']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:stalls,name'],
            'staff_id' => ['required', 'integer', Rule::exists('users', 'id')->where('user_type', 'staff')->where('is_active', true)],
            'delivery_enabled' => ['required', 'boolean'],
        ]);
        try {
            DB::transaction(function () use ($data, $request): void {
                $this->access->handle($request->user(), 'admin');
                $staff = User::whereKey($data['staff_id'])->where('user_type', 'staff')->where('is_active', true)->lockForUpdate()->first();
                if (! $staff) {
                    throw ValidationException::withMessages(['staff_id' => 'Choose an active staff account.']);
                }
                $stall = Stall::create(['name' => $data['name'], 'is_active' => false, 'delivery_enabled' => $data['delivery_enabled']]);
                $staff->stall_id = $stall->id;
                $staff->save();
                $staff->revokeDatabaseSessions();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This stall name is already in use.']);
        }

        return to_route('admin.stalls.index')->with('status', 'Stall created.');
    }

    public function updateStall(Request $request, Stall $stall): RedirectResponse
    {
        $request->merge(['stall_dialog' => 'edit-stall-'.$stall->id]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('stalls')->ignore($stall)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
        try {
            DB::transaction(function () use ($request, $stall, $data): void {
                $this->access->handle($request->user(), 'admin');
                Stall::whereKey($stall->id)->lockForUpdate()->firstOrFail()->update($data);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This stall name is already in use.']);
        }

        return to_route('admin.stalls.index')->with('status', 'Stall updated.');
    }

    public function reassignStall(Request $request, Stall $stall): RedirectResponse
    {
        $request->merge(['stall_dialog' => 'reassign-stall-'.$stall->id]);
        $data = $request->validate(['staff_id' => ['required', 'integer', Rule::exists('users', 'id')->where('user_type', 'staff')->where('is_active', true)]]);
        DB::transaction(function () use ($request, $stall, $data): void {
            $this->access->handle($request->user(), 'admin');
            $accounts = User::where(fn ($query) => $query->where('stall_id', $stall->id)->orWhere('id', $data['staff_id']))->orderBy('id')->lockForUpdate()->get();
            Stall::whereKey($stall->id)->lockForUpdate()->firstOrFail();
            $selected = $accounts->firstWhere('id', (int) $data['staff_id']);
            if (! $selected || ! $selected->is_active || $selected->user_type !== 'staff') {
                throw ValidationException::withMessages(['staff_id' => 'Choose an active staff account.']);
            }
            foreach ($accounts as $account) {
                if ($account->user_type === 'staff') {
                    $account->stall_id = $account->is($selected) ? $stall->id : null;
                    $account->save();
                    $account->revokeDatabaseSessions();
                }
            }
        }, 3);

        return to_route('admin.stalls.index')->with('status', 'Staff assignment updated.');
    }

    public function stallDelivery(Request $request, Stall $stall): RedirectResponse
    {
        $data = $request->validate(['delivery_enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $stall, $data): void {
            $this->access->handle($request->user(), 'admin');
            Stall::whereKey($stall->id)->lockForUpdate()->firstOrFail()->update($data);
        }, 3);

        return back()->with('status', 'Stall delivery availability updated.');
    }

    public function deleteStall(Request $request, Stall $stall): RedirectResponse
    {
        $image = DB::transaction(function () use ($request, $stall): ?string {
            $this->access->handle($request->user(), 'admin');
            $locked = Stall::whereKey($stall->id)->lockForUpdate()->firstOrFail();
            if ($locked->id === Stall::DEFAULT_ID || DB::table('menu_items')->where('stall_id', $locked->id)->exists()
                || $locked->orders()->exists() || User::where('stall_id', $locked->id)->exists()
                || DB::table('carts')->where('stall_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['stall' => 'This stall has linked records or is the default stall. Close it using Edit to preserve its history.']);
            }
            $locked->delete();

            return $locked->image_path;
        }, 3);
        if ($image !== null && str_starts_with($image, 'stalls/')) {
            Storage::disk('public')->delete($image);
        }

        return to_route('admin.stalls.index')->with('status', 'Empty stall deleted.');
    }

    public function edit(User $user): RedirectResponse
    {
        return to_route('admin.users.index', ['edit' => $user->id]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['editing_account' => $user->id]);
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => strtolower(trim($email))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'is_active' => ['required', 'boolean'],
            'user_type' => ['sometimes', 'required', 'in:customer,staff,admin'],
            'stall_id' => ['nullable', 'integer', 'exists:stalls,id'],
        ]);

        abort_if($user->is($request->user()) && ! $data['is_active'], 422, 'You cannot deactivate your own account.');

        try {
            DB::transaction(function () use ($request, $user, $data): void {
                $actorId = $request->user()->id;
                $accounts = User::whereKey([$actorId, $user->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $actor = $accounts->get($actorId);
                abort_unless($actor?->is_active && $actor->user_type === 'admin', 403);

                $account = $accounts->get($user->id);
                abort_unless($account, 404);
                $oldEmail = $account->email;
                $role = $data['user_type'] ?? $account->user_type;
                if ($account->is($actor) && $role !== 'admin') {
                    throw ValidationException::withMessages(['user_type' => 'You cannot remove your own administrator access.']);
                }
                $stallId = array_key_exists('stall_id', $data) ? $data['stall_id'] : ($account->user_type === 'staff' ? $account->stall_id : null);
                if ($role === 'staff' && $stallId === null) {
                    throw ValidationException::withMessages(['stall_id' => 'Assign a stall to this staff account.']);
                }
                $account->fill(['name' => $data['name'], 'email' => $data['email']]);
                $account->is_active = $data['is_active'];
                $account->user_type = $role;
                $account->stall_id = $role === 'staff' ? $stallId : null;

                if ($account->isDirty('email')) {
                    $account->email_verified_at = null;
                }

                $accessChanged = $account->isDirty(['email', 'is_active', 'stall_id', 'user_type']);
                $account->save();
                if ($accessChanged) {
                    $account->revokePasswordResetTokens($oldEmail);
                    $account->revokeDatabaseSessions();
                    if ($account->is($actor)) {
                        $request->session()->regenerate(true);
                    }
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email address is already in use.']);
        }

        return to_route('admin.users.index')->with('status', 'Account updated.');
    }

    public function resetPassword(Request $request, User $user, SendCanteenPasswordReset $resetLinks): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $resetLinks): void {
            $this->access->handle($request->user(), 'admin');
            $recipient = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $recipient->is_active) {
                throw ValidationException::withMessages(['account' => 'Activate the account before sending a password reset link.']);
            }
            $resetLinks->handle($recipient->email);
        }, 3);

        return to_route('admin.users.index')->with('status', 'A password reset link will be sent to the account email if it is eligible.');
    }

    public function sales(Request $request): View
    {
        $period = $this->reportPeriod($request);
        $totals = $this->salesTotals($period['start'], $period['end']);
        $hours = array_fill(0, 24, 0);
        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', order_date) AS INTEGER)"
            : 'HOUR(order_date)';
        $transactions = Order::where('order_date', '>=', $period['start']->copy()->utc())
            ->where('order_date', '<', $period['end']->copy()->utc())
            ->selectRaw($hourExpression.' AS utc_hour, COUNT(*) AS volume')->groupByRaw($hourExpression)->toBase()->get();
        foreach ($transactions as $transaction) {
            $localHour = Carbon::create(2000, 1, 1, (int) $transaction->utc_hour, 0, 0, 'UTC')
                ->timezone(config('canteen.timezone'))->hour;
            $hours[$localHour] += (int) $transaction->volume;
        }
        $stallRevenue = $this->receiptsFor($period['start'], $period['end'])
            ->join('orders', 'orders.id', '=', 'digital_receipts.order_id')
            ->selectRaw('orders.stall_id, COUNT(*) AS orders_count, SUM(total_paid) AS revenue')
            ->groupBy('orders.stall_id')->get()->keyBy('stall_id');

        return view('admin.sales', [
            'date' => $period['date'],
            'period' => $period['period'],
            'periodLabel' => $period['start']->format('M j, Y').' – '.$period['end']->copy()->subDay()->format('M j, Y'),
            'totals' => $totals,
            'receipts' => $this->receiptsFor($period['start'], $period['end'])->with('order.customer')
                ->latest('issue_date')->latest('id')->paginate(20)->withQueryString(),
            'reports' => $request->user()->salesReports()->latest()->limit(10)->get(),
            'hours' => $hours,
            'stalls' => Stall::orderBy('id')->get(),
            'stallRevenue' => $stallRevenue,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $period = $this->reportPeriod($request);
        $receipts = $this->receiptsFor($period['start'], $period['end'])->with([
            'order:id,reference_number,stall_id,customer_id,contact_name,fulfillment_method,payment_method,status',
            'order.stall:id,name',
            'order.customer:id,name',
        ]);

        return response()->streamDownload(function () use ($receipts): void {
            $output = fopen('php://output', 'w');
            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Order Reference', 'Stall Name', 'Customer Name', 'Fulfillment', 'Order Status', 'Payment Method', 'Payment Status', 'Total Amount (PHP)', 'Date & Time (Asia/Manila)'], ',', '"', '', "\r\n");
                foreach ($receipts->lazyById(500) as $receipt) {
                    $order = $receipt->order;
                    $payment = $order?->payment_method ?: ($order?->fulfillment_method === 'delivery' ? 'cash_on_delivery' : 'cash_at_pickup');
                    $row = [
                        $order?->displayReference() ?? 'Unavailable order',
                        $order?->stall?->name ?? 'Unavailable stall',
                        $order?->contact_name ?: ($order?->customer?->name ?? 'Unavailable customer'),
                        ucfirst($order?->fulfillment_method ?? 'Unknown'),
                        Order::STAGES[$order?->status] ?? 'Unknown',
                        match ($payment) {
                            'cash_pickup', 'cash_at_pickup' => 'Cash at Pickup',
                            'cash_delivery', 'cash_on_delivery' => 'Cash on Delivery',
                            default => ucwords(str_replace('_', ' ', $payment)),
                        },
                        'Paid',
                        number_format($receipt->total_paid, 2, '.', ''),
                        $receipt->issue_date->timezone(config('canteen.timezone'))->format('Y-m-d H:i:s'),
                    ];
                    fputcsv($output, array_map(fn (string $value): string => $this->csvCell($value), $row), ',', '"', '', "\r\n");
                }
            } finally {
                fclose($output);
            }
        }, "canteen-sales-{$period['date']}-{$period['period']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvCell(string $value): string
    {
        return preg_match('/\A[\s\x{FEFF}]*[=+@\-]/u', $value) ? "'".$value : $value;
    }

    public function report(Request $request): RedirectResponse
    {
        $period = $this->reportPeriod($request);
        DB::transaction(function () use ($request, $period): void {
            $actor = $this->access->handle($request->user(), 'admin');
            $totals = $this->salesTotals($period['start'], $period['end']);
            $actor->salesReports()->create([
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->copy()->subDay()->toDateString(),
                'total_orders' => $totals->placed_orders,
                'total_sales' => $totals->total_sales,
            ]);
        }, 3);

        return to_route('admin.sales.index', ['date' => $period['date'], 'period' => $period['period']])
            ->with('status', 'Sales report saved.');
    }

    /** @return array{start: Carbon, end: Carbon, date: string, period: string} */
    private function reportPeriod(Request $request): array
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2099-12-31'],
            'period' => ['nullable', 'in:day,week,month'],
        ]);
        $anchor = Carbon::parse($data['date'] ?? 'today', config('canteen.timezone'))->startOfDay();
        $period = $data['period'] ?? 'day';
        $start = match ($period) {
            'week' => $anchor->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $anchor->copy()->startOfMonth(),
            default => $anchor->copy(),
        };
        $end = match ($period) {
            'week' => $start->copy()->addWeek(),
            'month' => $start->copy()->addMonth(),
            default => $start->copy()->addDay(),
        };

        return ['start' => $start, 'end' => $end, 'date' => $anchor->toDateString(), 'period' => $period];
    }

    private function receiptsFor(Carbon $start, Carbon $end): Builder
    {
        return DigitalReceipt::query()
            ->where('issue_date', '>=', $start->copy()->utc())
            ->where('issue_date', '<', $end->copy()->utc());
    }

    private function salesTotals(Carbon $start, Carbon $end): object
    {
        $totals = $this->receiptsFor($start, $end)
            ->selectRaw('COUNT(*) AS total_orders, COALESCE(SUM(total_paid), 0) AS total_sales')->toBase()->first();
        $totals->placed_orders = Order::where('order_date', '>=', $start->copy()->utc())
            ->where('order_date', '<', $end->copy()->utc())->count();

        return $totals;
    }
}
