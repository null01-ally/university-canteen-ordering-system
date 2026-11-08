<div class="shift-summary-content">
    <p class="shift-summary-date">{{ $stall->name }} · {{ $summaryDate->format('d F Y') }}<small>Asia/Manila · Updated {{ $generatedAt->format('g:i:s A') }}</small></p>
    <div class="shift-financial-grid">
        <section class="shift-revenue"><h3>Total Revenue</h3><strong>Php {{ number_format($revenue, 2) }}</strong><p>Gross sales from today's completed orders.</p></section>
        <section class="shift-payment-card"><h3>Payment breakdown</h3><dl class="shift-payments"><div><dt>Cash Collected</dt><dd>Php {{ number_format($cashCollected, 2) }}</dd></div></dl><p class="shift-note">Cash collected is based on issued cash receipts for these orders.</p></section>
    </div>
    <section class="shift-section shift-order-metrics"><h3>Order metrics</h3><dl class="shift-metrics"><div><dt>Completed</dt><dd>{{ $completedCount }}</dd></div><div><dt>Cancelled</dt><dd>{{ $cancelledCount }}</dd></div><div><dt>Unclaimed</dt><dd>{{ $unclaimedCount }}</dd></div></dl><p class="shift-note">Unclaimed means ready orders awaiting collection or delivery. No-shows are not separately recorded.</p></section>
    <section class="shift-section shift-selling-card"><h3>Top selling items</h3><div class="shift-item-columns" aria-hidden="true"><span>Menu item</span><span>Portions sold</span></div><ol class="shift-top-items">@forelse ($topItems as $item)<li><span>{{ $item->item_name }}</span><strong>{{ $item->portions }} {{ Str::plural('portion', (int) $item->portions) }}</strong></li>@empty<li>No completed sales yet today.</li>@endforelse</ol></section>
    <p class="shift-note">All figures cover orders placed today at this stall. Revenue and item totals include completed orders only.</p>
    <a class="shift-print-link" href="{{ route('staff.summary', ['print' => 1]) }}" target="_blank" rel="noopener">Print / Export Daily Log</a>
</div>

