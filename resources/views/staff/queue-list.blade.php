<span hidden data-newest-order-id="{{ $newestOrderId }}"></span>
@php($selectedStage = in_array($status, ['pending', 'preparing', 'ready'], true) ? $status : 'pending')
<nav class="queue-tabs" role="tablist" aria-label="Kitchen order stages">
    @foreach ($lanes as $stage => $label)
        <a id="queue-tab-{{ $stage }}" href="{{ route('staff.orders.index', ['status' => $stage]) }}" role="tab" data-queue-tab="{{ $stage }}" aria-controls="queue-panel-{{ $stage }}" aria-selected="{{ $selectedStage === $stage ? 'true' : 'false' }}" tabindex="{{ $selectedStage === $stage ? '0' : '-1' }}">
            <span>{{ $label }}</span><strong>{{ $orders[$stage]->total() }}</strong>
        </a>
    @endforeach
</nav>
<div class="queue-panels">
    @foreach ($lanes as $stage => $label)
        <section id="queue-panel-{{ $stage }}" class="queue-panel" role="tabpanel" data-queue-panel="{{ $stage }}" aria-labelledby="queue-tab-{{ $stage }}" tabindex="0" @if ($selectedStage !== $stage) hidden @endif>
            
            <div class="queue-card-grid">
                @forelse ($orders[$stage] as $order)
                    <article class="kds-order">
                        <h3>{{ $order->displayReference() }}</h3>
                        <span class="kds-method">{{ strtoupper($order->fulfillment_method) }}</span>
                        <p class="kds-order-meta">{{ $order->order_date->timezone(config('canteen.timezone'))->format('d M Y · g:i:s A') }} · {{ $order->contact_name ?: $order->customer?->name }}</p>
                        @if ($order->delivery_location)<p class="font-semibold mt-2">{{ $order->delivery_location }}</p>@endif
                        @if ($order->notes)<p class="kds-instructions">{{ $order->notes }}</p>@endif
                        <ul class="kds-items">
                            @foreach ($order->lines as $line)
                                <li><span class="queue-item-check" aria-hidden="true">✓</span><strong>{{ $line->quantity }}x</strong> <span>{{ $line->item_name }}@if ($line->instructions)<small>{{ $line->instructions }}</small>@endif</span></li>
                            @endforeach
                        </ul>
                        <p class="kds-total">Cash {{ $order->fulfillment_method === 'delivery' ? 'on delivery' : 'at pickup' }} <strong>Php {{ number_format($order->total_amount, 2) }}</strong></p>
                        @if ($next = $order->nextStatus())
                        <form method="post" action="{{ route('staff.orders.update', $order->reference_number) }}" data-kds-order>
                            @csrf @method('PATCH')
                            <button type="submit" name="status" value="{{ $next }}" class="kds-action">{{ match ($next) { 'preparing' => 'Accept & Start', 'ready' => 'Mark Ready', 'completed' => 'Complete' } }}</button>
                            <p class="kds-feedback" role="status"></p>
                        </form>
                        @endif
                    </article>
                @empty
                    <div class="queue-empty">
    <img src="{{ asset('images/icons/queue-list-1.png') }}" alt="" aria-hidden="true" width="28" height="34" class="ui-icon object-contain " decoding="async">
    <h2>All caught up!</h2><p>{{ match ($stage) { 'pending' => 'No pending orders right now.', 'preparing' => 'No orders are cooking right now.', 'ready' => 'No orders waiting for pickup or delivery.' } }}</p>
</div>
                @endforelse
            </div>
            @if ($orders[$stage]->hasPages())
            <nav class="mt-4 flex flex-wrap gap-3 items-center" aria-label="{{ $label }} pages">
                @if ($orders[$stage]->previousPageUrl())<a class="button-secondary" href="{{ $orders[$stage]->previousPageUrl() }}">Previous</a>@endif
                <span>{{ $orders[$stage]->currentPage() }} / {{ $orders[$stage]->lastPage() }}</span>
                @if ($orders[$stage]->nextPageUrl())<a class="button-secondary" href="{{ $orders[$stage]->nextPageUrl() }}">Next</a>@endif
            </nav>
            @endif
        </section>
    @endforeach
</div>


