<img src="{{ asset('images/icons/notification-icon-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async">
@if ($unreadCount > 0)
    <span class="customer-notification-badge" aria-hidden="true">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
@endif

