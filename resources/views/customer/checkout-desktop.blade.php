<div class="customer-desktop-only desktop-checkout">
    <a class="desktop-back-link" href="{{ route('menu.index', ['stall' => $stall->id]) }}">← Back to menu</a>
    <div class="desktop-page-heading"><div><h1>Review &amp; checkout</h1><p>One last check before we prepare your campus favorites.</p></div><p class="text-xs">Cart　/　<strong class="text-maroon-800">Checkout</strong>　/　Confirmation</p></div>
    <form id="desktop-service-form" method="post" action="{{ route('cart.service') }}" data-async-form>@csrf<input type="hidden" name="return_to" value="checkout"></form>
    <form action="{{ route('orders.store') }}" method="post" class="desktop-checkout-grid" id="desktop-checkout-form">
        @csrf<input type="hidden" name="snapshot" value="{{ $snapshot }}">
        <div class="desktop-card checkout-details-card">
            <section class="checkout-details-section"><h2>Customer details</h2><div class="mt-4 grid grid-cols-2 gap-6"><label class="desktop-field-label">Full Name<input class="desktop-field" name="contact_name" value="{{ old('contact_name', auth()->user()->name) }}" autocomplete="name" maxlength="255" required></label><label class="desktop-field-label">Contact Number<input class="desktop-field" type="tel" name="contact_phone" value="{{ old('contact_phone', auth()->user()->contact_phone) }}" autocomplete="tel" maxlength="32" @required($method === 'delivery')></label></div></section>
            <section class="checkout-details-section"><div class="flex items-center justify-between gap-3"><h2>How would you like your order?</h2><p class="text-xs text-maroon-800">Typical prep: {{ $stall->preparation_minutes }} minutes</p></div>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    @foreach (['pickup' => ['Pickup', 'Collect at the stall · Free'], 'delivery' => ['Delivery', 'Delivered within campus']] as $value => [$label, $hint])
                        <button type="submit" form="desktop-service-form" name="method" value="{{ $value }}" @class(['desktop-choice', 'is-selected' => $method === $value]) @disabled(! ($value === 'pickup' ? $pickupEnabled : $deliveryEnabled)) aria-pressed="{{ $method === $value ? 'true' : 'false' }}"><span><strong>{{ $label }}</strong><small>{{ $hint }}</small></span><span class="desktop-radio" aria-hidden="true"></span></button>
                    @endforeach
                </div>
                <div class="mt-4 grid grid-cols-2 gap-6">
                    @if ($method === 'delivery')<label class="desktop-field-label">Delivery location<input class="desktop-field" name="delivery_location" value="{{ old('delivery_location') }}" placeholder="Campus building, room, or landmark" maxlength="255" required></label>@else<label class="desktop-field-label">Pickup address<input class="desktop-field" value="{{ $stall->name }} · {{ $stall->location ?: 'Stall counter' }}" readonly></label>@endif
                    <label class="desktop-field-label">Store Notes<input id="desktop-store-notes" class="desktop-field" name="notes" value="{{ old('notes') }}" placeholder="Add a note for the stall" maxlength="1000"></label>
                </div>
            </section>
            <section class="checkout-details-section"><h2>Payment method</h2><div class="mt-4 grid grid-cols-2 gap-4">
                @foreach (['pickup' => ['Cash at Pickup', 'Pay at the stall when you collect'], 'delivery' => ['Cash on Delivery', 'For delivery orders only']] as $value => [$label, $hint])<label @class(['desktop-choice', 'is-selected' => $method === $value, 'opacity-60' => $method !== $value])><span><strong>{{ $label }}</strong><small>{{ $hint }}</small></span><input class="accent-maroon-800" type="radio" name="payment_method" value="{{ $value === 'pickup' ? 'cash_at_pickup' : 'cash_on_delivery' }}" @checked($method === $value) @disabled($method !== $value)></label>@endforeach
                </div><p class="mt-4 text-[11px] text-stone-600">No online payment required. Please prepare the exact amount if possible.</p>
            </section>
        </div>
        <aside class="desktop-card desktop-checkout-summary">
            <div class="flex items-center justify-between"><h2>Order Summary</h2><span class="text-[11px] text-maroon-800">{{ $lines->sum('quantity') }} items</span></div><p class="mt-4 text-xs text-stone-600">{{ $stall->name }}@if ($stall->location) · {{ $stall->location }}@endif</p>
            <div class="desktop-service-hint mt-4 !text-maroon-800"><strong>{{ ucfirst($method) }} · Today</strong></div>
            <ul class="desktop-summary-items">@foreach ($lines as $line)<li>@include('menu-image', ['menuImageItem' => $line->item, 'menuImageClass' => 'aspect-square rounded-lg object-cover', 'menuImageWidth' => 56, 'menuImageHeight' => 56])<div><h3>{{ $line->quantity }} × {{ $line->item?->name ?? 'Removed menu item' }}</h3><p>{{ $line->item?->description }}</p>@if ($line->instructions)<p class="basket-instructions">{{ $line->instructions }}</p>@endif</div><strong>Php {{ number_format($line->subtotalInCents() / 100, 2) }}</strong></li>@endforeach</ul>
            <dl class="desktop-summary-math"><div><dt>Subtotal ({{ $lines->sum('quantity') }} items)</dt><dd>Php {{ number_format($subtotal, 2) }}</dd></div><div><dt>{{ $method === 'delivery' ? 'Delivery' : 'Pickup' }} fee</dt><dd class="text-maroon-800">{{ $fee > 0 ? 'Php '.number_format($fee, 2) : 'Php 0.00 · Free' }}</dd></div><div class="desktop-grand-total"><dt>Grand total</dt><dd>Php {{ number_format($total, 2) }}</dd></div></dl>
            <p class="my-4 text-xs text-stone-600">{{ $method === 'delivery' ? 'Cash on Delivery' : 'Cash at Pickup' }} · Pay Php {{ number_format($total, 2) }} {{ $method === 'delivery' ? 'when it arrives.' : 'at the counter.' }}</p>
            <button class="desktop-maroon-button w-full" @disabled(! $canCheckout)>Place Order</button><p class="mt-4 text-center text-[10px] text-stone-600">Please check your details before placing your order.</p>
        </aside>
    </form>
</div>





