@extends('layout')
@section('title', 'Sales overview')
@section('workspace-controls')
<details class="workspace-report-period relative">
    <summary class="flex min-h-10 cursor-pointer list-none items-center gap-5 rounded-xl bg-stone-50 px-4 text-xs">{{ \Illuminate\Support\Carbon::parse($date)->format('j F Y') }}<span aria-hidden="true">⌄</span></summary>
    <form action="{{ route('admin.sales.index') }}" class="absolute right-0 z-20 mt-2 grid w-64 gap-3 rounded-2xl border border-maroon-100 bg-white p-4 shadow-lg">
        <label class="field-label">Sales date<input class="field" name="date" type="date" value="{{ $date }}" required></label>
        <label class="field-label">Reporting period<select class="field" name="period">@foreach (['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'] as $key => $label)<option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>@endforeach</select></label>
        <button class="button-primary">Apply</button>
    </form>
</details>
@endsection
@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="workspace-heading">Sales overview</h1><p class="mt-1 text-sm text-stone-600">A real-time pulse on campus dining.</p></div>
    <a class="button-gold !rounded-full !px-8" href="{{ route('admin.sales.export', ['date' => $date, 'period' => $period]) }}">↓ Export report</a>
</div>
@php
    $average = $totals->total_orders ? $totals->total_sales / $totals->total_orders : 0;
    $maxHours = max(1, max($hours));
    $maxRevenue = max(1, $stallRevenue->max('revenue') ?? 0);
    $points = collect($hours)->map(fn ($count, $hour) => round(26 + $hour * 23.8, 2).','.round(144 - 120 * $count / $maxHours, 2));
@endphp
<div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
    @foreach (['Total revenue' => 'Php '.number_format($totals->total_sales, 2), 'Orders placed' => number_format($totals->placed_orders), 'Active stalls' => $stalls->filter(fn ($stall) => $stall->isTakingOrders())->count(), 'Average paid order' => 'Php '.number_format($average, 2)] as $label => $value)
        <div class="workspace-kpi min-h-[126px]"><p class="workspace-kpi-label">{{ $label }}</p><p class="workspace-kpi-value">{{ $value }}</p></div>
    @endforeach
</div>
<p class="sr-only">{{ $periodLabel }} · Philippine time · {{ $totals->total_orders }} completed cash orders</p>
<div class="mb-6 grid gap-6 xl:grid-cols-[1.55fr_1fr]">
    <section class="workspace-card">
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-semibold">Peak hour transactions</h2>@if (max($hours) > 0)<span class="rounded-full bg-maroon-50 px-3 py-1 text-xs font-semibold text-maroon-800">Peak time {{ sprintf('%02d:00', array_search(max($hours), $hours, true)) }}</span>@endif</div>
        <p class="mt-1 text-xs text-stone-600">Orders placed by hour in the selected period</p>
        @if ($totals->placed_orders)
        <svg viewBox="0 0 600 170" class="mt-5 w-full text-maroon-800" role="img" aria-label="Hourly transactions: {{ implode(', ', $hours) }}">
            @foreach ([24, 64, 104, 144] as $y)<line x1="26" y1="{{ $y }}" x2="573.4" y2="{{ $y }}" stroke="#ecd8df"/>@endforeach
            <polyline points="{{ $points->implode(' ') }}" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            @foreach ([0, 4, 8, 12, 16, 20, 23] as $hour)<text x="{{ 26 + $hour * 23.8 }}" y="165" font-size="10" text-anchor="middle" fill="#57534e">{{ sprintf('%02d:00', $hour) }}</text>@endforeach
        </svg>
        @else
            <div class="mt-5 grid min-h-36 place-items-center rounded-xl border border-dashed border-stone-300 bg-canteen-cream px-4 text-center text-sm text-stone-600 xl:min-h-44">No orders placed in this period.</div>
        @endif
    </section>
    <section class="workspace-card">
        <h2 class="text-lg font-semibold">Revenue by stall</h2><p class="mt-1 text-xs text-stone-600">Php {{ number_format($totals->total_sales, 2) }} total</p>
        <ul class="mt-6 space-y-4">
            @foreach ($stalls as $stall)
                @php($revenue = $stallRevenue->get($stall->id)?->revenue ?? 0)
                <li class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] items-center gap-2 text-xs"><span>{{ $stall->name }}</span><div class="h-3.5 overflow-hidden rounded-full bg-canteen-cream" aria-hidden="true"><div @class(['h-full rounded-full', 'bg-maroon-800' => $loop->first, 'bg-gold-400' => ! $loop->first]) style="width: {{ 100 * $revenue / $maxRevenue }}%"></div></div><strong class="text-right tabular-nums">{{ number_format($revenue, 2) }}</strong></li>
            @endforeach
        </ul>
    </section>
</div>
<section class="workspace-card mb-6">
    <div class="mb-4 flex justify-between gap-3"><h2 class="text-lg font-semibold">Stall performance</h2><a class="text-link text-sm" href="{{ route('admin.stalls.index') }}">View all stalls →</a></div>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Stall performance table"><table class="workspace-table !min-w-[30rem]">
        <thead><tr><th>Stall</th><th>Orders</th><th>Revenue</th><th>Status</th></tr></thead>
        <tbody>@foreach ($stalls as $stall)<tr><td>{{ $stall->name }}</td><td>{{ $stallRevenue->get($stall->id)?->orders_count ?? 0 }}</td><td class="font-semibold text-maroon-800">Php {{ number_format($stallRevenue->get($stall->id)?->revenue ?? 0, 2) }}</td><td>{{ $stall->isTakingOrders() ? 'Active' : 'Closed' }}</td></tr>@endforeach</tbody>
    </table></div>
</section>
<details class="mt-6"><summary class="cursor-pointer text-sm font-semibold text-maroon-800">Cash transactions &amp; saved reports</summary><section class="workspace-card mt-4" id="reports">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Cash transactions</h2><form method="post" action="{{ route('admin.sales.store') }}">@csrf<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="period" value="{{ $period }}"><button class="button-secondary">Save report</button></form></div>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Cash transactions table"><table class="workspace-table !min-w-[32rem]">
        <thead><tr><th>Receipt</th><th>Customer</th><th>Time</th><th>Cash paid</th></tr></thead>
        <tbody>@forelse ($receipts as $receipt)<tr><td>{{ $receipt->receipt_number }}</td><td>{{ $receipt->order->customer->name }}</td><td>{{ $receipt->issue_date->timezone(config('canteen.timezone'))->format('M j, g:i A') }}</td><td>Php {{ number_format($receipt->total_paid, 2) }}</td></tr>@empty<tr><td colspan="4">No completed cash orders for this period.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $receipts->links() }}
    <h3 class="mt-5 mb-2 text-sm font-semibold">Recent saved reports</h3>
    <ul class="space-y-1 text-xs text-stone-600">@forelse ($reports as $report)<li>{{ $report->period_start->format('M j, Y') }} · {{ $report->total_orders }} orders · Php {{ number_format($report->total_sales, 2) }}</li>@empty<li>No saved reports yet.</li>@endforelse</ul>
</section></details>
@endsection


