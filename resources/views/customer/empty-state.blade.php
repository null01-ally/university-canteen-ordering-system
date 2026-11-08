<div class="customer-empty">
    <img src="{{ asset('images/icons/empty-state-1.png') }}" alt="" aria-hidden="true" width="96" height="80" class="ui-icon object-contain " decoding="async">
    <h3>{{ $emptyTitle ?? 'Your basket is waiting' }}</h3>
    <p>{{ $emptyDescription ?? 'Find something to eat.' }}</p>
    @unless ($hideEmptyAction ?? false)
        <a class="experience-button experience-button-outline" href="{{ $emptyUrl ?? route('stalls.index') }}">{{ $emptyAction ?? 'Browse Stalls' }}</a>
    @endunless
</div>

