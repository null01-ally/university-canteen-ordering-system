@extends('layout')
@section('title', 'Digital cash receipt')
@section('compact-layout', 'true')
@section('main-class', 'customer-flow')
@section('content')
<div class="print:hidden">@include('mobile-heading', ['back' => route('orders.index', ['history' => 1]), 'heading' => 'Cash receipt'])</div>
<section class="rounded-2xl border border-maroon-100 bg-white p-5">
    <h1 class="text-xl font-bold text-maroon-800">{{ $order->stall->name }}</h1><p class="mt-1 text-xs text-stone-600">Digital cash receipt · {{ $order->displayReference() }}</p>
    <p class="mt-3 break-all text-xs text-stone-600">{{ $order->receipt->receipt_number }}</p><p class="mt-1 text-xs text-stone-600">{{ $order->receipt->issue_date->timezone(config('canteen.timezone'))->format('d M Y · g:i A') }}</p>
    <ul class="my-5 space-y-3 border-y border-maroon-100 py-5 text-sm">@foreach ($order->lines as $line)<li class="flex justify-between gap-3"><span>{{ $line->quantity }} × {{ $line->item_name }}</span><span class="shrink-0">Php {{ number_format($line->quantity * $line->unit_price, 2) }}</span></li>@endforeach</ul>
    <dl class="space-y-3 text-sm"><div class="flex justify-between"><dt>Delivery fee</dt><dd>Php {{ number_format($order->delivery_fee, 2) }}</dd></div><div class="flex justify-between font-bold text-maroon-800"><dt>Cash paid</dt><dd>Php {{ number_format($order->receipt->total_paid, 2) }}</dd></div></dl>
    <p class="mt-5 text-xs text-stone-600">{{ $order->fulfillment_method === 'delivery' ? 'Cash on Delivery' : 'Cash at Pickup' }} · Payment received</p>
</section>
<button class="design-primary mt-6 w-full print:hidden" type="button" data-print-report>Print / Save receipt</button>
@endsection

