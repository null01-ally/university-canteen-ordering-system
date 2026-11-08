@extends('layout')
@section('title', 'Track your order')
@section('compact-layout', 'true')
@section('live-page', 'true')
@section('main-class', 'customer-flow customer-tracking')
@section('content')
@php
    $delivery = $order->fulfillment_method === 'delivery';
    $labels = ['pending' => 'Order Placed', 'preparing' => 'Kitchen Preparing', 'ready' => $delivery ? 'Ready for delivery' : 'Ready for pickup', 'completed' => 'Completed'];
    $position = array_search($order->status, array_keys($labels), true);
@endphp
<span hidden data-order-stage="{{ $order->status }}" data-order-reference="{{ $order->reference_number }}" data-order-label="{{ $labels[$order->status] ?? 'Cancelled' }}"></span>
@include('customer.order-desktop')
<div class="customer-mobile-only mobile-tracking-content">
@include('mobile-heading', ['back' => route('orders.index'), 'heading' => 'Track your order'])
@include('customer.order-header')
<section class="mt-4 rounded-2xl border border-maroon-100 bg-white p-4">
    <h2 class="mb-3 text-sm font-semibold">Your order</h2>
    <ul class="space-y-2 text-xs text-stone-600">
        @foreach ($order->lines as $line)
            <li class="flex justify-between gap-3"><span>{{ $line->quantity }} × {{ $line->item_name }}</span><span class="shrink-0 text-stone-900">Php {{ number_format($line->quantity * $line->unit_price, 2) }}</span></li>
        @endforeach
    </ul>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-maroon-100 pt-4"><span class="text-xs text-stone-600">Total @if ($delivery) · incl. Php {{ number_format($order->delivery_fee, 2) }} delivery @endif</span><strong class="text-maroon-800">Php {{ number_format($order->total_amount, 2) }}</strong></div>
    @if ($order->receipt)
        <button class="experience-button experience-button-outline mt-4 w-full" type="button" data-open-receipt>View Digital Receipt</button>
        <p class="mt-3 text-xs wrap-anywhere text-stone-600">Digital receipt · {{ $order->receipt->receipt_number }}</p>
    @endif
</section>
@if ($order->status === 'cancelled')
    @if ($order->cancellation_reason)<p class="mt-4 text-sm text-stone-600">{{ $order->cancellation_reason }}</p>@endif
@else
<section class="mt-6">
    <h2 class="text-lg font-semibold">Order progress</h2>
    <div class="sr-only">@include('order-status', ['status' => $order->status])</div>
    @include('customer.progress-steps')
</section>
@endif
<section class="mt-5 rounded-xl border border-gray-200 bg-white p-4">
    <h2 class="text-sm font-semibold">{{ $delivery ? 'Delivery details' : 'Collection details' }}</h2>
    <p class="mt-2 text-xs leading-5 text-stone-600">{{ $delivery ? ($order->delivery_location ?: 'Campus delivery') : 'Collect at '.($order->stall->location ?: $order->stall->name).' counter. Show '.$order->displayReference().'.' }}</p>
    <p class="mt-3 text-sm">{{ $order->contact_name ?: auth()->user()->name }}</p>
    @if ($order->contact_phone)<p class="mt-1 text-xs text-stone-600">{{ $order->contact_phone }}</p>@endif
    @if ($order->notes)<p class="mt-3 text-xs text-stone-600">{{ $order->notes }}</p>@endif
</section>
@if ($order->status === 'pending')
    <details class="mt-6 rounded-xl border border-maroon-100 bg-white p-4">
        <summary class="cursor-pointer text-sm font-semibold text-maroon-800">Cancel order</summary>
        <p class="mt-2 text-xs text-stone-600">You can cancel before the stall starts preparing your order.</p>
        <form method="post" action="{{ route('orders.cancel', $order->reference_number) }}" data-confirm-cancellation class="mt-3">
            @csrf @method('PATCH')
            <button class="button-secondary">Confirm cancellation</button>
        </form>
    </details>
@endif
<div class="tracking-actions mt-10 grid grid-cols-[1fr_1.25fr] gap-3">
    @if ($order->stall->phone)<a class="design-secondary" href="tel:{{ preg_replace('/[^0-9+]/', '', $order->stall->phone) }}">Call store</a>@else<a class="design-secondary" href="{{ route('orders.show', $order->reference_number) }}">Refresh status</a>@endif
    @if ($order->status === 'completed')<form method="post" action="{{ route('orders.again', $order->reference_number) }}">@csrf<button class="experience-button experience-button-maroon w-full">Order Again</button></form>@else<a class="design-primary" href="{{ route('menu.index', ['stall' => $order->stall_id]) }}">Back to menu</a>@endif
</div>
</div>
@include('customer.receipt-drawer')
@endsection








