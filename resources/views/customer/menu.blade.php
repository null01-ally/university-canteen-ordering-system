@extends('layout')
@section('title', $stall->name)
@section('compact-layout', 'true')
@section('live-page', 'true')
@section('main-class', 'customer-menu')

@section('page-header')
<header class="customer-menu-header mb-5">
    <div class="flex items-center gap-1">
        <a class="menu-icon -ml-2" href="{{ route('stalls.index') }}" aria-label="Choose a stall" title="Choose a stall">
            <img src="{{ asset('images/icons/menu-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async">
        </a>
        <h1 class="min-w-0 flex-1 text-[1.375rem] font-bold wrap-anywhere text-maroon-800">{{ $stall->name }}</h1>
        @include('customer.order-history-link')
        <details class="relative">
            <summary class="customer-notification-button list-none" aria-label="{{ $unreadCount > 0 ? 'Notifications, '.$unreadCount.' unread' : 'Notifications' }}" title="Notifications">
                @include('customer.notification-icon')
            </summary>
            <div data-notification-preview class="customer-notification-dropdown absolute top-full right-0 z-20 mt-2 max-h-80 w-[min(19rem,calc(100vw-3rem))] overflow-auto rounded-2xl border border-maroon-100 bg-canteen-surface p-4 shadow-lg">
                <p class="mb-2 font-bold text-maroon-800">Order notifications</p>
                @if ($notifications->isEmpty())
                    <p class="text-sm text-stone-600">{{ $unreadCount > 0 ? 'View all notifications for your latest updates.' : 'You are all caught up.' }}</p>
                @else
                    @include('customer.notification-preview')
                @endif
                <a class="text-link mt-3 block text-sm" href="{{ route('notifications.index') }}">All notifications</a>
            </div>
        </details>
        @include('customer.account-link')

    </div>
    <p class="mt-1 flex items-center gap-2 text-xs text-stone-600">
        <span @class(['size-1.5 rounded-full', 'bg-green-700' => $stall->isTakingOrders(), 'bg-stone-400' => ! $stall->isTakingOrders()]) aria-hidden="true"></span>
        {{ $stall->location ? $stall->location.' · ' : '' }}{{ $stall->isTakingOrders() ? 'Open for orders' : 'Currently closed' }}
    </p>
</header>
@endsection

@section('content')
@include('customer.menu-content')
@endsection


