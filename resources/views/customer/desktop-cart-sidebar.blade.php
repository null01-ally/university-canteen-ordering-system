<aside class="customer-desktop-only desktop-card customer-cart-sidebar" aria-label="Current order">
    <div class="flex items-center justify-between gap-3"><h2 class="basket-heading">Your basket</h2><span class="desktop-small-badge" data-cart-count-items>{{ $cartCount }} items</span><button type="button" class="basket-panel-toggle" data-basket-toggle aria-expanded="true" aria-controls="basket-panel" aria-label="Collapse basket"><img src="{{ asset('images/icons/desktop-cart-sidebar-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain " decoding="async"></button></div><div id="basket-panel" class="basket-panel">
    <p class="mt-1 text-xs text-stone-600">{{ $orderStall->name }}@if ($orderStall->location) · {{ $orderStall->location }}@endif</p>
    <form class="desktop-service-switch" action="{{ route('cart.service') }}" method="post" data-async-form data-refresh-current>@csrf
        @foreach (['pickup' => 'Pickup', 'delivery' => 'Delivery'] as $value => $label)<button name="method" value="{{ $value }}" @class(['is-selected' => $orderMethod === $value]) @disabled(! $orderStall->accepts($value)) aria-pressed="{{ $orderMethod === $value ? 'true' : 'false' }}">{{ $label }}</button>@endforeach
    </form>
    <div class="desktop-service-hint"><strong>{{ $orderStall->isTakingOrders() ? 'Typical preparation: '.$orderStall->preparation_minutes.' minutes' : 'Stall currently closed' }}</strong><small>{{ $orderMethod === 'pickup' ? 'Collect at '.$orderStall->name.' counter' : 'Delivery within approved campus locations' }}</small></div>
    <div class="desktop-sidebar-lines">
        @forelse ($cartLines as $line)
            <div class="desktop-sidebar-line" data-cart-item="{{ $line->item_id }}" data-cart-url="{{ route('cart.quantity', $line->item_id) }}" data-cart-price="{{ (int) str_replace('.', '', $line->item?->price ?? '0.00') }}" data-cart-max="{{ min(999, $line->item?->is_available ? $line->item->stock_qty : 0) }}">
                <div class="flex items-start justify-between gap-3"><div><h3>{{ $line->item?->name ?? 'Removed menu item' }}</h3><p>{{ $line->item?->description }}</p>@if ($line->instructions)<p class="basket-instructions">{{ $line->instructions }}</p>@endif</div><form method="post" action="{{ route('cart.destroy', $line) }}" data-async-form data-cart-control data-refresh-current>@csrf @method('DELETE')<button class="desktop-remove" aria-label="Remove {{ $line->item?->name }}">×</button></form></div>
                <div class="mt-1.5 flex items-center justify-between gap-3"><div class="desktop-stepper"><form action="{{ route('cart.update', $line) }}" method="post" data-async-form data-cart-control data-refresh-current>@csrf @method('PATCH')<button name="step" value="-1" aria-label="Decrease {{ $line->item?->name }} quantity">−</button></form><span data-cart-quantity>{{ $line->quantity }}</span><form action="{{ route('cart.update', $line) }}" method="post" data-async-form data-cart-control data-refresh-current>@csrf @method('PATCH')<button name="step" value="1" aria-label="Increase {{ $line->item?->name }} quantity" @disabled(! $line->item?->canOrder($line->quantity + 1))>+</button></form></div><strong class="text-xs" data-cart-line-total>Php {{ number_format($line->subtotalInCents() / 100, 2) }}</strong></div>
            </div>
        @empty @include('customer.empty-state', ['hideEmptyAction' => true])@endforelse
        @if ($cartLines->isNotEmpty())<a class="mt-2 inline-block text-[11px] text-maroon-800" href="{{ route('checkout.index') }}#desktop-store-notes">Add a note for the stall</a>@endif
    </div>
    <div class="desktop-sidebar-total">
        <dl class="space-y-2.5 text-xs"><div class="flex justify-between"><dt data-cart-subtotal-label>Subtotal ({{ $cartCount }} items)</dt><dd data-cart-subtotal>Php {{ number_format($cartSubtotal, 2) }}</dd></div><div class="flex justify-between"><dt>{{ $orderMethod === 'delivery' ? 'Delivery' : 'Pickup' }} fee</dt><dd class="text-maroon-800">{{ $cartFee > 0 ? 'Php '.number_format($cartFee, 2) : 'Free' }}</dd></div><div class="flex items-center justify-between pt-1 text-base font-semibold"><dt>Total</dt><dd class="text-2xl font-bold text-maroon-800" data-cart-total>Php {{ number_format($cartSubtotal + $cartFee, 2) }}</dd></div></dl>
        @php($basketCanCheckout = $cartLines->isNotEmpty() && $orderStall->accepts($orderMethod) && $cartLines->every(fn ($line) => $line->item?->canOrder($line->quantity) ?? false))
        <a class="desktop-maroon-button mt-3 w-full" data-basket-checkout data-checkout-url="{{ route('checkout.index') }}" data-service-available="{{ $orderStall->accepts($orderMethod) ? 'true' : 'false' }}" role="link" aria-disabled="{{ $basketCanCheckout ? 'false' : 'true' }}" @if ($basketCanCheckout) href="{{ route('checkout.index') }}" @else tabindex="-1" @endif>Checkout →</a><p class="mt-3 text-center text-[10px] text-stone-600">Review your order and payment at checkout.</p>
    </div>
</div></aside>





