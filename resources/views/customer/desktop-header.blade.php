<header class="customer-desktop-header" data-customer-header>
    <a class="customer-desktop-brand" href="{{ route('stalls.index') }}">@include('seal', ['sealClass' => 'size-12'])<span><strong>ZPPSU</strong><small>CANTEEN SYSTEM</small></span></a>
    <nav aria-label="Customer navigation" class="customer-desktop-icons">
        <a class="menu-icon" href="{{ route('stalls.index') }}" aria-label="Browse stalls" title="Browse stalls" @if (request()->routeIs('stalls.*', 'menu.*')) aria-current="page" @endif>
            <img src="{{ asset('images/icons/desktop-header-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async">
        </a>
        @include('customer.order-history-link')
        <a href="{{ route('notifications.index') }}" class="customer-notification-button" aria-label="{{ $desktopUnreadCount > 0 ? 'Notifications, '.$desktopUnreadCount.' unread' : 'Notifications' }}" title="Notifications" @if (request()->routeIs('notifications.*')) aria-current="page" @endif>
            @include('customer.notification-icon', ['unreadCount' => $desktopUnreadCount])
        </a>
        @include('customer.account-link')
    </nav>
</header>

