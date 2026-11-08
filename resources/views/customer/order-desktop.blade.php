<div class="customer-desktop-only desktop-tracking">
    <div class="desktop-page-heading"><h1>Track your order</h1><a class="desktop-back-link" href="{{ route('menu.index', ['stall' => $order->stall_id]) }}">← Back to menu</a></div>
    @include('customer.order-header')
    @if ($order->status !== 'cancelled')
        <section class="desktop-card minimal-progress-card">@include('customer.progress-steps')<p class="minimal-live-label">Live updates · Every 8 seconds</p></section>
    @endif
    <div class="desktop-tracking-grid">
        <section class="desktop-card">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2>Your order</h2><span class="text-xs text-stone-600">{{ $order->receipt ? 'Paid in cash' : ($order->status === 'cancelled' ? 'No payment collected' : 'Unpaid · Cash due '.($delivery ? 'on delivery' : 'at pickup')) }}</span></div>
            <ul class="desktop-summary-items">@foreach ($order->lines as $line)<li>@include('menu-image', ['menuImageItem' => $line->item, 'menuImageClass' => 'aspect-square rounded-lg object-cover', 'menuImageWidth' => 56, 'menuImageHeight' => 56])<div><h3>{{ $line->quantity }} × {{ $line->item_name }}</h3>@if ($line->instructions)<p class="basket-instructions">{{ $line->instructions }}</p>@endif</div><strong>Php {{ number_format($line->quantity * $line->unit_price, 2) }}</strong></li>@endforeach</ul>
            <dl class="desktop-summary-math">@if ($order->delivery_fee > 0)<div><dt>Items</dt><dd>Php {{ number_format($order->total_amount - $order->delivery_fee, 2) }}</dd></div><div><dt>Delivery</dt><dd>Php {{ number_format($order->delivery_fee, 2) }}</dd></div>@endif<div class="desktop-grand-total"><dt>Total</dt><dd>Php {{ number_format($order->total_amount, 2) }}</dd></div></dl>
            @if ($order->receipt)<button type="button" class="desktop-outline-button mt-5 w-full" data-open-receipt>View Digital Receipt</button>@elseif ($order->status !== 'cancelled')<button type="button" class="desktop-outline-button mt-5 w-full" disabled>Receipt · Available after payment</button>@endif
        </section>
        <aside class="desktop-card">
            <h2>{{ $delivery ? 'Delivery details' : 'Collection details' }}</h2>
            <p class="my-4 text-sm leading-6 text-stone-600">{{ $order->status === 'cancelled' ? ($order->cancellation_reason ?: 'This order was cancelled.') : ($delivery ? ($order->delivery_location ?: 'Campus delivery') : 'Collect at '.($order->stall->location ?: $order->stall->name).' counter. Show '.$order->displayReference().'.') }}</p>
            <dl class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm"><div><dt class="mb-1 text-xs text-stone-600">Customer</dt><dd>{{ $order->contact_name ?: auth()->user()->name }}</dd></div><div><dt class="mb-1 text-xs text-stone-600">Contact number</dt><dd>{{ $order->contact_phone ?: 'Not provided' }}</dd></div>@if ($order->notes)<div class="col-span-2"><dt class="mb-1 text-xs text-stone-600">Store notes</dt><dd>{{ $order->notes }}</dd></div>@endif</dl>
            <div class="mt-5 grid grid-cols-2 gap-3">@if ($order->stall->phone)<a class="desktop-outline-button" href="tel:{{ preg_replace('/[^0-9+]/', '', $order->stall->phone) }}">Call store</a>@else<a class="desktop-outline-button" href="{{ route('orders.show', $order->reference_number) }}">Refresh status</a>@endif<a class="desktop-maroon-button" href="{{ route('orders.index') }}">My Orders</a></div>
            @if ($order->status === 'completed')<form method="post" action="{{ route('orders.again', $order->reference_number) }}" class="mt-4">@csrf<button class="experience-button experience-button-maroon w-full">Order Again</button></form>@endif
            @if ($order->status === 'pending')<details class="mt-5"><summary class="cursor-pointer text-xs text-maroon-800">Cancel order</summary><form action="{{ route('orders.cancel', $order->reference_number) }}" data-confirm-cancellation method="post" class="mt-3">@csrf @method('PATCH')<button class="desktop-outline-button">Confirm cancellation</button></form></details>@endif
        </aside>
    </div>
</div>




