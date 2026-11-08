@extends('layout')
@section('title', 'Choose your stall')
@section('compact-layout', 'true')
@section('live-page', 'true')
@section('main-class', 'customer-flow customer-stalls')
@section('content')
<header class="mb-6 flex items-center gap-3 text-maroon-800 lg:hidden">
    @include('seal', ['sealClass' => 'size-9'])
    <a class="min-w-0 flex-1 text-sm leading-tight font-bold" href="{{ route('stalls.index') }}">ZPPSU<span class="hidden min-[360px]:inline"> Canteen</span></a>
    @include('customer.order-history-link')
    <a href="{{ route('notifications.index') }}" class="customer-notification-button" aria-label="{{ $unreadCount > 0 ? 'Notifications, '.$unreadCount.' unread' : 'Notifications' }}" title="Notifications">@include('customer.notification-icon')</a>
    @include('customer.account-link')
</header>
<section class="rounded-2xl bg-maroon-800 p-5 text-white">
    <h1 class="text-2xl font-bold">Welcome, {{ Str::before(auth()->user()->name, ' ') }}</h1>
</section>
<div class="mt-5 flex gap-3">
    <form class="flex min-w-0 flex-1" action="{{ route('stalls.index') }}">
        <input type="search" name="search" aria-label="Find a stall or craving" value="{{ $search }}" placeholder="Find a stall or craving..." class="design-field mt-0" maxlength="100">
    </form>
</div>
<section class="mt-6">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 class="text-[1.375rem] font-bold">Choose your stall</h2><p class="text-xs font-semibold text-green-800">{{ $openCount }} {{ Str::plural('stall', $openCount) }} open</p></div>
    <div class="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-4">
        @forelse ($stalls as $stall)
            <a href="{{ route('menu.index', ['stall' => $stall->id]) }}" class="rounded-2xl border border-maroon-100 bg-white p-2 hover:bg-maroon-50">
                @if ($stall->imageUrl())<img src="{{ $stall->imageUrl() }}" class="aspect-[1.8] w-full rounded-xl object-cover" alt="">@else<div class="grid aspect-[1.8] place-items-center rounded-xl bg-canteen-cream text-maroon-800" aria-hidden="true"><img src="{{ asset('images/icons/stalls-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-9" decoding="async"></div>
                @endif
                <h3 class="px-1 pt-2 text-sm font-bold wrap-anywhere text-maroon-800">{{ $stall->name }}</h3>
                <p class="px-1 pt-1 pb-2 text-xs text-stone-600">{{ $stall->isTakingOrders() ? ($stall->description ?: 'Open for orders') : 'Currently closed' }}</p>
            </a>
        @empty
            <div class="col-span-full">@include('customer.empty-state', ['emptyTitle' => 'No stalls found.', 'emptyDescription' => 'Try another name or explore all campus stalls.', 'emptyAction' => 'Clear Filters'])</div>
        @endforelse
    </div>
</section>
@endsection





