@extends('layout')
@section('title', 'Order review')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow order-review')
@section('content')
@include('mobile-heading', ['back' => route('menu.index', ['stall' => $stall->id]), 'heading' => 'Order review', 'subheading' => $stall->name.' · '.$lines->sum('quantity').' items'])
<div class="order-review-grid">
<div class="min-w-0">
<div class="customer-service-banner flex items-center gap-4 rounded-2xl bg-maroon-800 px-4 py-3.5 text-white">
    <span class="text-gold-400">@include('customer.service-icon')</span><div><p class="font-semibold">{{ $method === 'delivery' ? 'Deliver' : 'Pickup' }} · Today</p>
    <p class="mt-1 text-xs text-maroon-100">{{ $method === 'delivery' ? 'Enter your campus location at checkout' : 'Collect at '.$stall->name.' when ready' }}</p>
</div></div>
@if ($deliveryEnabled || $method === 'delivery')
    <details class="customer-service-options"><summary>Change service</summary><form data-async-form method="post" action="{{ route('cart.service') }}" class="mt-3 flex gap-2">
        @csrf
        @if ($pickupEnabled)<button name="method" value="pickup" @class(['menu-pill', 'menu-pill-active' => $method === 'pickup'])>Pickup</button>@endif
        @if ($deliveryEnabled)<button name="method" value="delivery" @class(['menu-pill', 'menu-pill-active' => $method === 'delivery'])>Delivery</button>@endif
    </form></details>
@endif
<ul class="customer-cart-lines mt-4">
    @forelse ($lines as $line)
        <li class="grid grid-cols-[72px_minmax(0,1fr)] gap-3.5 py-3" data-cart-item="{{ $line->item_id }}" data-cart-url="{{ route('cart.quantity', $line->item_id) }}" data-cart-price="{{ (int) str_replace('.', '', $line->item?->price ?? '0.00') }}" data-cart-max="{{ min(999, $line->item?->is_available ? $line->item->stock_qty : 0) }}">
            @include('menu-image', ['menuImageItem' => $line->item, 'menuImageClass' => 'size-[72px] aspect-square rounded-xl object-cover', 'menuImageWidth' => 72, 'menuImageHeight' => 72])
            <div class="min-w-0">
                <h2 class="text-sm font-semibold wrap-anywhere">{{ $line->item?->name ?? 'Removed menu item' }}</h2>
                <p class="mt-1 text-xs wrap-anywhere text-stone-600">{{ $line->item?->description }}</p>@if ($line->instructions)<p class="basket-instructions">{{ $line->instructions }}</p>@endif
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <strong class="text-sm text-maroon-800" data-cart-line-total>Php {{ number_format(($line->item?->price ?? 0) * $line->quantity, 2) }}</strong>
                    <div class="cart-stepper flex items-center rounded-full border border-maroon-100 text-sm">
                        <form data-async-form data-cart-control method="post" action="{{ route('cart.update', $line) }}">
                            @csrf @method('PATCH')
                            <button name="step" value="-1" class="size-11 font-semibold text-maroon-800" aria-label="{{ $line->quantity > 1 ? 'Decrease quantity' : 'Remove item' }}">−</button>
                        </form>
                        <span class="w-8 text-center font-semibold" data-cart-quantity>{{ $line->quantity }}</span>
                        <form data-async-form data-cart-control method="post" action="{{ route('cart.update', $line) }}">
                            @csrf @method('PATCH')
                            <button name="step" value="1" class="size-11 font-semibold text-maroon-800 disabled:text-stone-400" aria-label="Increase quantity" @disabled($line->quantity >= 999 || ! $line->item?->canOrder($line->quantity + 1))>+</button>
                        </form>
                    </div>
                </div>
                @if (! $line->item?->canOrder($line->quantity))<p class="mt-2 text-xs text-maroon-800">Selected quantity unavailable.</p>@endif
            </div>
        </li>
    @empty
        <li>@include('customer.empty-state')</li>
    @endforelse
</ul>
</div>
<section class="order-summary" aria-label="Order summary">
<dl class="mt-5 space-y-3 border-t border-maroon-100 pt-5 text-sm">
    <div class="flex justify-between"><dt class="text-stone-600">Subtotal</dt><dd data-cart-subtotal>Php {{ number_format($subtotal, 2) }}</dd></div>
    <div class="flex justify-between"><dt class="text-stone-600">Delivery fee</dt><dd>Php {{ number_format($fee, 2) }}</dd></div>
    <div class="flex justify-between pt-2 text-xl font-bold"><dt>Total</dt><dd class="text-maroon-800" data-cart-total>Php {{ number_format($total, 2) }}</dd></div>
</dl>
<p class="mt-4 text-xs text-stone-600">{{ $method === 'delivery' ? 'Includes campus delivery. Pay when it arrives.' : 'No delivery fee. Pay at the stall when you collect.' }}</p>
@if (! $canCheckout && $lines->isNotEmpty())<p class="mt-2 text-xs text-maroon-800">Review unavailable items or choose an available service to continue.</p>@endif
<div class="review-dock">
    <div class="mx-auto grid max-w-[31rem] grid-cols-[1fr_1.9fr] gap-3 md:grid-cols-1 lg:grid-cols-[1fr_1.9fr]">
        <a class="design-secondary" href="{{ route('menu.index', ['stall' => $stall->id]) }}">Add items</a>
        @if ($canCheckout)<a class="design-primary" data-cart-checkout-label href="{{ route('checkout.index') }}">Checkout · Php {{ number_format($total, 2) }}</a>@else<button class="design-primary" disabled>Checkout</button>@endif
    </div>
</div>
</section>
</div>
@include('customer.cart-state', ['cartLines' => $lines, 'cartFee' => $fee, 'cartContext' => 'cart'])
@endsection





