@extends('layout')
@section('title', 'Sales reports')
@section('content')
<div class="workspace-page-title print:hidden">
    <div><h1 class="workspace-heading">Sales reports</h1><p>Configure your report, review the totals, then export.</p></div>
    <div class="flex flex-wrap gap-4">
        <a class="button-secondary" href="{{ route('admin.reports.index', array_merge(request()->except('page'), ['format' => 'csv'])) }}">Export CSV</a>
        <a class="button-gold" href="{{ route('admin.reports.index', array_merge(request()->except('page'), ['format' => 'print'])) }}">Print / Save PDF</a>
    </div>
</div>
<form action="{{ route('admin.reports.index') }}" class="workspace-card mb-6 print:hidden">
    <div class="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1.1fr_1.1fr_auto]">
        <label class="field-label">From date<input class="field" type="date" name="from" value="{{ $from }}" required></label>
        <label class="field-label">To date<input class="field" type="date" name="to" value="{{ $to }}" required></label>
        <label class="field-label">Stall<select class="field" name="stall"><option value="">All stalls</option>@foreach ($stalls as $stall)<option value="{{ $stall->id }}" @selected((string) request('stall') === (string) $stall->id)>{{ $stall->name }}</option>@endforeach</select></label>
        <label class="field-label">Order status<select class="field" name="status"><option value="completed">Completed</option></select></label>
        <button class="button-primary">Update preview</button>
    </div>
    <p class="mt-5 text-xs text-stone-600">All times in Asia/Manila · Cash payments from completed orders</p>
</form>
<div class="mb-6 grid gap-5 sm:grid-cols-3">
    @foreach (['Total sales' => ['Php '.number_format($total, 2), 'Cash payments from completed orders'], 'Completed orders' => [number_format($count), 'Across '.$rows->count().' campus stalls'], 'Average order value' => ['Php '.number_format($count ? $total / $count : 0, 2), 'Total sales ÷ completed orders']] as $label => [$value, $hint])
        <div class="workspace-kpi"><p class="workspace-kpi-label">{{ $label }}</p><p class="workspace-kpi-value">{{ $value }}</p><p class="mt-1 text-xs text-stone-600">{{ $hint }}</p></div>
    @endforeach
</div>
<section class="workspace-card" @if ($printing) data-print-report-page @endif>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3"><div><h2 class="workspace-section-title">Report preview</h2><p class="mt-1 text-xs text-stone-600">{{ $from }} – {{ $to }} · {{ request('stall') ? $stalls->firstWhere('id', (int) request('stall'))?->name : 'All stalls' }} · Completed orders</p></div><span class="workspace-badge bg-status-ready-bg text-status-ready-text">Ready to export</span></div>
    <div class="overflow-x-auto"><table class="workspace-table report-table"><thead><tr><th>Stall</th><th>Orders</th><th>Total sales</th><th>Avg. order</th></tr></thead><tbody>
        @forelse ($rows as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->orders_count }}</td><td class="font-semibold text-maroon-800">Php {{ number_format($row->revenue, 2) }}</td><td>Php {{ number_format($row->revenue / $row->orders_count, 2) }}</td></tr>@empty<tr><td colspan="4">No completed cash transactions match these filters.</td></tr>@endforelse
    </tbody><tfoot><tr><td>Total</td><td>{{ $count }}</td><td>Php {{ number_format($total, 2) }}</td><td>Php {{ number_format($count ? $total / $count : 0, 2) }}</td></tr></tfoot></table></div>
</section>
<p class="mt-4 text-xs text-stone-600">Preview excludes cancelled and unpaid orders. Exports use the filters shown above.</p>
@endsection
