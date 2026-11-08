<?php

namespace App\Http\Controllers;

use App\Models\DigitalReceipt;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Stall;
use App\Services\CancelCanteenOrder;
use App\Services\LockCanteenAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffOrderController extends Controller
{
    public function __construct(private LockCanteenAccount $access) {}

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Order::STAGES))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filter['status'] ?? null;
        $search = $filter['search'] ?? null;
        $stall = $request->user()->stall;
        $orderQuery = Order::select([
            'id', 'reference_number', 'customer_id', 'stall_id', 'status', 'order_date',
            'fulfillment_method', 'contact_name', 'delivery_location', 'notes', 'total_amount',
        ])->with(['customer:id,name', 'lines:id,order_id,item_name,quantity,unit_price,instructions'])
            ->where('stall_id', $stall->id)
            ->when($search !== null, function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('reference_number', "%{$search}%")
                        ->orWhereLike('contact_name', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->whereLike('name', "%{$search}%"));
                    if (preg_match('/\A#?BB-(\d{1,18})\z/i', $search, $matches)) {
                        $query->orWhere('orders.id', (int) $matches[1]);
                    }
                });
            });
        $lanes = [
            'pending' => 'New Orders',
            'preparing' => 'Cooking / Preparing',
            'ready' => 'Ready / Out for Delivery',
        ];
        $orders = collect($lanes)->mapWithKeys(function (string $label, string $stage) use ($orderQuery, $request): array {
            $pageName = $stage.'_page';
            $page = (clone $orderQuery)->where('status', $stage)
                ->orderBy('order_date')->orderBy('id')
                ->paginate(5, ['*'], $pageName)
                ->appends(array_merge($request->only(['search', 'pending_page', 'preparing_page', 'ready_page']), ['status' => $stage]));

            return [$stage => $page];
        });

        return view($request->boolean('fragment') ? 'staff.queue-list' : 'staff.queue', [
            'orders' => $orders,
            'status' => $status,
            'search' => $search,
            'lanes' => $lanes,
            'stall' => $stall,
            'newestOrderId' => $stall->orders()->whereIn('status', ['pending', 'preparing', 'ready'])->max('id') ?? 0,
        ]);
    }

    public function summary(Request $request): View
    {
        $request->validate(['print' => ['nullable', 'boolean']]);
        $stall = $request->user()->stall;
        $start = now(config('canteen.timezone'))->startOfDay();
        $summary = DB::transaction(function () use ($stall, $start): array {
            $daily = Order::where('stall_id', $stall->id)
                ->where('order_date', '>=', $start->copy()->utc())
                ->where('order_date', '<', $start->copy()->addDay()->utc());
            $completed = (clone $daily)->where('status', 'completed');
            $counts = (clone $daily)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

            return [
                'revenue' => (clone $completed)->sum('total_amount'),
                'cashCollected' => DigitalReceipt::whereIn('order_id', (clone $completed)->select('id'))->sum('total_paid'),
                'completedCount' => (int) ($counts['completed'] ?? 0),
                'cancelledCount' => (int) ($counts['cancelled'] ?? 0),
                'unclaimedCount' => (int) ($counts['ready'] ?? 0),
                'topItems' => OrderLine::whereIn('order_id', (clone $completed)->select('id'))
                    ->selectRaw('item_id, MAX(item_name) AS item_name, SUM(quantity) AS portions')
                    ->groupBy('item_id')->orderByDesc('portions')->orderBy('item_id')->limit(5)->get(),
            ];
        });

        return view($request->boolean('print') ? 'staff.summary-print' : 'staff.summary', $summary + [
            'stall' => $stall,
            'summaryDate' => $start,
            'generatedAt' => now(config('canteen.timezone')),
        ]);
    }

    public function delivery(Request $request): RedirectResponse
    {
        $data = $request->validate(['delivery_enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $data): void {
            $actor = $this->access->handle($request->user(), 'staff', $request->user()->stall_id);
            Stall::whereKey($actor->stall_id)->lockForUpdate()->firstOrFail()->update($data);
        }, 3);

        return back()->with('status', 'Campus delivery settings updated.');
    }

    public function settings(Request $request): View
    {
        return view('staff.stalls', ['stall' => $request->user()->stall()->firstOrFail()]);
    }

    public function updateStall(Request $request, Stall $stall): RedirectResponse|JsonResponse
    {
        abort_unless($stall->id === $request->user()->stall_id, 404);

        if ($request->has('name')) {
            return $this->updateConfiguration($request, $stall);
        }

        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'delivery_enabled' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $stall, $data): void {
            $this->access->handle($request->user(), 'staff', $stall->id);
            Stall::whereKey($stall->id)->lockForUpdate()->firstOrFail()->update($data);
        }, 3);

        if ($request->expectsJson()) {
            return response()->json(['state' => $stall->fresh()->only(['is_active', 'delivery_enabled'])]);
        }

        return back()->with('status', 'Stall status updated.');
    }

    public function configuration(Stall $stall): View
    {
        return view('admin.stall-settings', ['stall' => $stall]);
    }

    public function updateConfiguration(Request $request, Stall $stall): RedirectResponse
    {
        $isStaff = $request->user()->user_type === 'staff';
        abort_if($isStaff && $request->user()->stall_id !== $stall->id, 404);

        if ($request->has('weekly_groups')) {
            $groups = $request->validate([
                'weekly_groups' => ['required', 'array:weekdays,saturday,sunday', 'size:3'],
                'weekly_groups.*' => ['required', 'array:enabled,opens,closes'],
                'weekly_groups.*.enabled' => ['required', 'boolean'],
                'weekly_groups.*.opens' => ['required', 'date_format:H:i'],
                'weekly_groups.*.closes' => ['required', 'date_format:H:i', 'after:weekly_groups.*.opens'],
            ])['weekly_groups'];
            $hours = [];
            foreach (range(1, 7) as $day) {
                $hours[$day] = $groups[$day < 6 ? 'weekdays' : ($day === 6 ? 'saturday' : 'sunday')];
            }
            $request->merge(['weekly_hours' => $hours]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('stalls')->ignore($stall)],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'preparation_minutes' => ['sometimes', 'integer', 'min:1', 'max:180'],
            'weekly_hours' => ['sometimes', 'array:1,2,3,4,5,6,7', 'size:7'],
            'weekly_hours.*' => ['array:enabled,opens,closes'],
            'weekly_hours.*.enabled' => ['required', 'boolean'],
            'weekly_hours.*.opens' => ['required', 'date_format:H:i'],
            'weekly_hours.*.closes' => ['required', 'date_format:H:i', 'after:weekly_hours.*.opens'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+() -]{7,32}$/'],
            'is_active' => ['required', 'boolean'],
            'pickup_enabled' => ['required', 'boolean'],
            'delivery_enabled' => ['required', 'boolean'],
            'delivery_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'opens_at' => ['nullable', 'required_with:closes_at,break_starts_at,break_ends_at', 'date_format:H:i'],
            'closes_at' => ['nullable', 'required_with:opens_at', 'date_format:H:i', 'after:opens_at'],
            'break_starts_at' => ['nullable', 'required_with:break_ends_at', 'date_format:H:i', 'after_or_equal:opens_at', 'before:closes_at'],
            'break_ends_at' => ['nullable', 'required_with:break_starts_at', 'date_format:H:i', 'after:break_starts_at', 'before_or_equal:closes_at'],
            'photo' => ['nullable', 'prohibited_if:remove_photo,1', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'remove_photo' => ['sometimes', 'boolean'],
        ]);
        unset($data['photo'], $data['remove_photo']);
        $photo = $request->file('photo')?->store('stalls', 'public');
        if ($photo === false) {
            throw ValidationException::withMessages(['photo' => 'The stall image could not be saved. Please try again.']);
        }
        $previousImage = null;
        try {
            DB::transaction(function () use ($request, $stall, $data, $photo, &$previousImage): void {
                $isStaff = $request->user()->user_type === 'staff';
                $this->access->handle($request->user(), $isStaff ? 'staff' : 'admin', $isStaff ? $stall->id : null);
                $lockedStall = Stall::whereKey($stall->id)->lockForUpdate()->firstOrFail();
                $previousImage = $lockedStall->image_path;
                $lockedStall->fill($data);
                if ($photo !== null || $request->boolean('remove_photo')) {
                    $lockedStall->image_path = $photo;
                }
                $lockedStall->save();
            }, 3);
        } catch (\Throwable $exception) {
            if ($photo !== null) {
                Storage::disk('public')->delete($photo);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['name' => 'This stall name is already in use.']);
            }
            throw $exception;
        }
        if (($photo !== null || $request->boolean('remove_photo')) && $previousImage !== null && str_starts_with($previousImage, 'stalls/')) {
            Storage::disk('public')->delete($previousImage);
        }

        return to_route($isStaff ? 'staff.stalls.index' : 'admin.stalls.index')->with('status', 'Stall settings updated.');
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->stall_id === $request->user()->stall_id, 404);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Order::STAGES))]]);

        DB::transaction(function () use ($request, $order, $data): void {
            $actor = $this->access->handle($request->user(), 'staff', $order->stall_id);
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->stall_id === $actor->stall_id, 404);
            $status = $data['status'];

            if ($lockedOrder->status === $status) {
                return;
            }

            if ($lockedOrder->nextStatus() !== $status) {
                throw ValidationException::withMessages(['status' => 'This order has changed. Refresh the queue before updating it.']);
            }

            $lockedOrder->update(['status' => $status]);

            if ($status === 'preparing') {
                $lockedOrder->customer->pickupNotifications()->create([
                    'order_id' => $lockedOrder->id,
                    'message' => 'The kitchen is preparing order '.$lockedOrder->displayReference().'. Your campus favorites are on their way.',
                ]);
            }

            if ($status === 'ready') {
                $reference = $lockedOrder->displayReference();
                $message = $lockedOrder->fulfillment_method === 'delivery'
                    ? "Order {$reference} is ready for campus delivery. Please prepare your cash payment."
                    : "Order {$reference} is ready. Pay in cash at the canteen when you pick it up.";

                $lockedOrder->customer->pickupNotifications()->create([
                    'order_id' => $lockedOrder->id,
                    'message' => $message,
                ]);
            }

            if ($status === 'completed') {
                $lockedOrder->customer->pickupNotifications()->where('is_read', false)
                    ->where(fn ($query) => $query->where('order_id', $lockedOrder->id)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('order_id')->whereLike('message', "Order {$lockedOrder->reference_number} is ready%")))
                    ->update(['is_read' => true]);

                $lockedOrder->receipt()->create([
                    'receipt_number' => 'RC-'.Str::ulid(),
                    'issue_date' => now(),
                    'total_paid' => $lockedOrder->total_amount,
                ]);
                $lockedOrder->customer->pickupNotifications()->create([
                    'order_id' => $lockedOrder->id,
                    'message' => 'Order '.$lockedOrder->displayReference().' is completed. Your digital cash receipt is now available.',
                ]);
            }
        }, 3);

        return back()->with('status', 'Order updated.');
    }

    public function cancel(Request $request, Order $order, CancelCanteenOrder $cancellation): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $cancellation->handle($order, $request->user(), $reason);

        return back()->with('status', 'Order cancelled and stock restored.');
    }
}
