@extends('layout')
@section('title', 'Past Orders')
@section('compact-layout', 'true')
@section('live-page', 'true')
@section('main-class', 'customer-flow customer-history')
@section('content')
@include('customer.orders-desktop')
<div class="customer-mobile-only">
@include('mobile-heading', ['back' => route('stalls.index'), 'heading' => request()->boolean('history') ? 'Past Orders' : 'My orders'])
<p class="mb-4 text-xs text-stone-600">Your campus orders, all in one place.</p>
<nav class="mb-5 grid grid-cols-3 gap-2" aria-label="Order history filters">
    @foreach (['' => 'All orders', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)<a href="{{ route('orders.index', array_filter(['history' => request('history'), 'status' => $value])) }}" @class(['menu-pill !min-h-9', 'menu-pill-active' => (request('status', '') === $value)]) @if (request('status', '') === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
@include('customer.notifications')
<section aria-label="Order history" class="space-y-3">
    @forelse ($orders as $order)
        <article class="rounded-2xl border border-maroon-100 bg-white px-4 py-3">
            <div class="flex items-center justify-between gap-2"><h2 class="font-bold">{{ $order->displayReference() }}</h2>@include('order-status', ['status' => $order->status])</div>
            <p class="mt-1 text-xs text-stone-600">{{ $order->order_date->timezone(config('canteen.timezone'))->format('d M Y · g:i A') }}</p>
            <div class="mt-3 flex items-start justify-between gap-3 text-sm"><span>{{ $order->stall->name }} · {{ ucfirst($order->fulfillment_method) }}</span><strong class="shrink-0 text-maroon-800">Php {{ number_format($order->total_amount, 2) }}</strong></div>
            @if ($order->status === 'cancelled')<p class="mt-1 text-xs text-stone-600">{{ $order->cancellation_reason ?: 'Order cancelled' }}</p><p class="mt-3 text-xs text-stone-600">No payment collected · No receipt issued</p>
            @else<p class="mt-1 text-xs text-stone-600">{{ $order->lines_sum_quantity ?? 0 }} items · {{ $order->fulfillment_method === 'delivery' ? 'Cash on delivery' : 'Cash at stall' }}</p>
                <a class="design-secondary mt-4 w-full !min-h-11" href="{{ $order->receipt ? route('orders.receipt', $order->reference_number) : route('orders.show', $order->reference_number) }}">{{ $order->receipt ? 'View digital cash receipt' : 'Track your order' }}</a>
            @endif
            @if ($order->status === 'cancelled')<a class="mt-2 inline-block text-xs text-maroon-800" href="{{ route('orders.show', $order->reference_number) }}">View order details →</a>@endif
        </article>
    @empty<p class="rounded-2xl border border-maroon-100 bg-white p-5 text-sm text-stone-600">You haven’t placed any orders yet.</p><a class="design-primary w-full" href="{{ route('stalls.index') }}">Browse stalls</a>@endforelse
</section>
<p class="mt-6 text-xs text-stone-600">Showing {{ $orders->count() }} of {{ $orders->total() }} orders</p><div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection

