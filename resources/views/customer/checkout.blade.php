@extends('layout')
@section('title', 'Place order')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow order-review')
@section('content')
@include('customer.checkout-desktop')
<div class="customer-mobile-only">
@include('mobile-heading', ['back' => route('cart.index'), 'heading' => 'Place order'])
<div class="customer-checkout-service flex items-center gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-maroon-800">
    @include('customer.service-icon')<div><p class="font-semibold">{{ $method === 'delivery' ? 'Deliver' : 'Pickup' }} · Today</p>
    <p class="mt-1 text-xs text-stone-600">{{ $stall->name }}</p>
</div></div>
@if ($pickupEnabled && $deliveryEnabled)
    <details class="customer-service-options"><summary>Change service</summary><form method="post" action="{{ route('cart.service') }}" class="mt-3 flex-wrap flex gap-2" data-async-form>
        @csrf
        <input type="hidden" name="return_to" value="checkout">
        <button class="menu-pill {{ $method === 'pickup' ? 'menu-pill-active' : '' }}" name="method" value="pickup">Cash at Pickup</button>
        <button class="menu-pill {{ $method === 'delivery' ? 'menu-pill-active' : '' }}" name="method" value="delivery">Campus Delivery</button>
    </form></details>
@endif
<form method="post" action="{{ route('orders.store') }}" class="order-review-grid customer-checkout-grid mt-6">
    @csrf
    <input type="hidden" name="snapshot" value="{{ $snapshot }}">
    <div class="min-w-0">
    <h2 class="mb-3 text-xl font-bold">{{ $method === 'delivery' ? 'Delivery details' : 'Pickup details' }}</h2>
    <div class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white px-4">
        <label class="block py-2.5 text-xs text-stone-600">Name<input class="mt-1 block w-full py-1 text-sm text-stone-900" name="contact_name" value="{{ old('contact_name', auth()->user()->name) }}" autocomplete="name" required maxlength="255"></label>
        <label class="block py-2.5 text-xs text-stone-600">Mobile number<input class="mt-1 block w-full py-1 text-sm text-stone-900" name="contact_phone" type="tel" value="{{ old('contact_phone', auth()->user()->contact_phone) }}" placeholder="Enter your mobile number" autocomplete="tel" maxlength="32" @required($method === 'delivery')></label>
        @if ($method === 'delivery')
            <label class="block py-2.5 text-xs text-stone-600">Delivery location
                <input class="mt-1 block w-full py-1 text-sm text-stone-900" name="delivery_location" value="{{ old('delivery_location') }}" placeholder="Campus building, room, or landmark" maxlength="255" required>
            </label>
        @endif
        <label class="block py-2.5 text-xs text-stone-600">Store notes<textarea class="mt-1 block w-full py-1 text-sm text-stone-900" name="notes" rows="1" maxlength="1000" placeholder="Add an optional note">{{ old('notes') }}</textarea></label>
    </div>
    </div>
    <section class="order-summary md:pt-5" aria-label="Payment and total">
    <h2 class="mb-3 text-xl font-bold">Payment method</h2>
    <div class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white px-4">
        @foreach (['delivery' => 'Cash on Delivery', 'pickup' => 'Cash at Pickup'] as $payment => $label)
            <label class="flex gap-3 py-3 text-sm font-semibold has-disabled:text-stone-600">
                <input type="radio" name="payment_method" value="{{ $payment === 'delivery' ? 'cash_on_delivery' : 'cash_at_pickup' }}" @checked($method === $payment) @disabled($method !== $payment) class="mt-1 accent-maroon-800">
                <span>{{ $label }}<small class="mt-1 block text-xs font-normal text-stone-600">{{ $payment === 'delivery' ? 'Pay your campus delivery runner.' : 'Pay at the stall counter.' }}</small></span>
            </label>
        @endforeach
    </div>
    <p class="my-5 flex flex-wrap items-center justify-between gap-2 text-xs text-stone-600"><span>{{ $lines->sum('quantity') }} items @if ($fee > 0) + Php {{ number_format($fee, 2) }} delivery @endif</span><strong class="text-xl text-maroon-800">Php {{ number_format($total, 2) }}</strong></p>
    <p class="mb-3 text-center text-xs text-stone-600">Please check your details before placing your order.</p>
    <button class="design-primary w-full" @disabled(! $canCheckout)>✓ Place order · Php {{ number_format($total, 2) }}</button>
    </section>
</form>
</div>
@endsection





