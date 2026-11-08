<section class="minimal-order-header">
    <div class="minimal-order-heading"><span class="minimal-order-check" aria-hidden="true">{{ $order->status === 'cancelled' ? '×' : '✓' }}</span><div><h2>{{ $order->status === 'cancelled' ? 'Order cancelled' : 'Order confirmed!' }}</h2><p><strong>{{ $order->displayReference() }}</strong> · {{ $order->order_date->timezone(config('canteen.timezone'))->format('d M Y, g:i A') }}</p></div></div>
    <div class="minimal-order-meta"><span class="minimal-status">{{ $order->status === 'ready' ? ($delivery ? 'Ready for delivery' : 'Ready for pickup') : \App\Models\Order::STAGES[$order->status] }}</span>
        @if (in_array($order->status, ['pending', 'preparing']))<p>Estimated kitchen ready · {{ $order->order_date->copy()->addMinutes($order->stall->preparation_minutes)->timezone(config('canteen.timezone'))->format('g:i A') }}</p>@endif
        <p>{{ $order->stall->name }} · {{ $delivery ? 'Delivery' : 'Pickup' }}</p>
    </div>
</section>


