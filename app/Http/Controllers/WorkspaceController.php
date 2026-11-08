<?php

namespace App\Http\Controllers;

use App\Models\DigitalReceipt;
use App\Models\Stall;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceController extends Controller
{
    public function reports(Request $request): View|StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2099-12-31'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2099-12-31'],
            'stall' => ['nullable', 'integer', 'exists:stalls,id'],
            'status' => ['nullable', 'in:completed'],
            'format' => ['nullable', 'in:csv,print'],
        ]);
        $from = Carbon::parse($data['from'] ?? $data['to'] ?? 'today', config('canteen.timezone'))->startOfDay();
        $to = Carbon::parse($data['to'] ?? $from->toDateString(), config('canteen.timezone'))->startOfDay();
        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => 'The end date must follow the start date.']);
        }
        $rows = DigitalReceipt::query()->join('orders', 'orders.id', '=', 'digital_receipts.order_id')
            ->join('stalls', 'stalls.id', '=', 'orders.stall_id')->where('orders.status', 'completed')
            ->where('issue_date', '>=', $from->copy()->utc())->where('issue_date', '<', $to->copy()->addDay()->utc())
            ->when($data['stall'] ?? null, fn (Builder $query, $stall) => $query->where('orders.stall_id', $stall))
            ->selectRaw('stalls.id, stalls.name, COUNT(*) AS orders_count, SUM(total_paid) AS revenue')
            ->groupBy('stalls.id', 'stalls.name')->orderBy('stalls.id')->get();
        $total = $rows->sum('revenue');
        $count = $rows->sum('orders_count');
        if (($data['format'] ?? '') === 'csv') {
            return response()->streamDownload(function () use ($rows, $from, $to): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['From', 'To', 'Stall', 'Completed orders', 'Cash collected (PHP)', 'Average order (PHP)'], ',', '"', '');
                foreach ($rows as $row) {
                    $name = $this->csvCell($row->name);
                    fputcsv($output, [$from->toDateString(), $to->toDateString(), $name, $row->orders_count, $row->revenue, number_format($row->revenue / $row->orders_count, 2, '.', '')], ',', '"', '');
                }
                fclose($output);
            }, 'canteen-sales-'.$from->toDateString().'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.reports', ['rows' => $rows, 'total' => $total, 'count' => $count, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'stalls' => Stall::orderBy('id')->get(), 'printing' => ($data['format'] ?? '') === 'print']);
    }

    public function audit(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'result' => ['nullable', 'in:success,failed'],
            'export' => ['nullable', 'boolean'],
        ]);
        $date = Carbon::parse($filters['date'] ?? 'today', config('canteen.timezone'))->startOfDay();
        $query = DB::table('audit_events')->where('created_at', '>=', $date->copy()->utc())->where('created_at', '<', $date->copy()->addDay()->utc());
        $stats = (clone $query)->selectRaw("COUNT(*) AS total, SUM(CASE WHEN event = 'Failed sign-in' THEN 1 ELSE 0 END) AS failures, SUM(CASE WHEN event LIKE 'admin.%' THEN 1 ELSE 0 END) AS changes")->first();
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->whereLike('actor', "%{$search}%")->orWhereLike('event', "%{$search}%")->orWhereLike('target', "%{$search}%")))
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['result'] ?? null, fn ($q, $result) => $q->where('result', $result))->orderByDesc('id');
        if ($request->boolean('export')) {
            return response()->streamDownload(function () use ($query): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Time (Asia/Manila)', 'Actor', 'Event', 'Target', 'Result'], ',', '"', '');
                foreach ($query->cursor() as $event) {
                    $cells = [Carbon::parse($event->created_at, 'UTC')->timezone(config('canteen.timezone'))->toDateTimeString(), $event->actor, $event->event, $event->target, $event->result];
                    fputcsv($output, array_map(fn ($value) => $this->csvCell((string) $value), $cells), ',', '"', '');
                }
                fclose($output);
            }, 'canteen-audit-'.$date->toDateString().'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.audit', ['events' => $query->paginate(12)->withQueryString(), 'eventTypes' => DB::table('audit_events')->distinct()->orderBy('event')->pluck('event'), 'stats' => $stats, 'date' => $date->toDateString()]);
    }

    private function csvCell(string $value): string
    {
        return preg_match('/\A[\s\x{FEFF}]*[=+@\-]/u', $value) ? "'".$value : $value;
    }
}
