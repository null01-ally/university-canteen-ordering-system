<header class="mb-5">
    <div class="flex min-h-11 items-center gap-3">
        <a href="{{ $back }}" class="menu-icon -ml-2 !size-9" aria-label="{{ $backLabel ?? 'Back' }}"><img src="{{ asset('images/icons/mobile-heading-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-5" decoding="async"></a>
        <h1 class="min-w-0 flex-1 text-[1.375rem] font-bold text-maroon-800">{{ $heading }}</h1>
        @if (auth()->user()?->user_type === 'customer' && ! request()->routeIs('account.*'))
            <div class="shrink-0 lg:hidden">@include('customer.account-link')</div>
        @endif
    </div>
    @isset($subheading)<p class="mt-1 text-xs text-stone-600">{{ $subheading }}</p>@endisset
</header>
