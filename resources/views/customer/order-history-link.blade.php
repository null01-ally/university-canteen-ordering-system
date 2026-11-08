<a href="{{ route('customer.orders.index') }}" class="menu-icon customer-order-history" data-order-history-link aria-label="Order history" title="Order history" @if (request()->routeIs('customer.orders.*', 'orders.*')) aria-current="page" @endif>
    <img src="{{ asset('images/icons/order-history-link-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async">
</a>

