<div class="menu-banner h-2 !min-h-0 !p-0" aria-hidden="true"></div>
<div class="customer-desktop-only desktop-stall-heading">
    <div><h1>{{ $stall->name }} <small>{{ $stall->isTakingOrders() ? 'Open' : 'Closed' }}</small></h1><p>{{ $stall->location ?: 'Campus canteen' }} · {{ $stall->isTakingOrders() ? 'Open for orders' : 'Currently closed' }}</p></div>
    <form action="{{ route('menu.index') }}" method="get" data-live-menu-search><input type="hidden" name="stall" value="{{ $stall->id }}"><input class="field" name="search" type="search" placeholder="Search" aria-label="Search food, drinks and more" value="{{ $search }}" maxlength="100"></form>
</div>
<details id="menu-search" class="menu-search" @if ($search !== '') open @endif>
    <summary>Search the menu</summary>
    <form method="get" action="{{ route('menu.index') }}" data-live-menu-search><input type="hidden" name="stall" value="{{ $stall->id }}"><input class="experience-input" id="menu-search-input" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="What are you craving?" aria-label="Search by item name"><button class="experience-button experience-button-maroon">Search</button></form>
</details>
<nav aria-label="Menu categories" data-menu-categories class="experience-categories">
    <a class="menu-pill menu-pill-active" aria-current="page" href="#menu-title" data-category-target="menu-title">All</a>
    @foreach ($categories as $category)
        <a class="menu-pill" href="{{ route('menu.index', ['stall' => $stall->id] + ($search !== '' ? ['search' => $search] : [])) }}#category-{{ $category->id }}" data-category-target="category-{{ $category->id }}">{{ $category->name }}</a>
    @endforeach
</nav>
<div data-menu-results class="experience-menu-results">
    <div class="menu-results-heading"><h2 id="menu-title">The daily favorites</h2><span>{{ $items->filter(fn ($item) => $item->canOrder())->count() }} items available</span></div>
    <div class="fetch-skeleton" aria-hidden="true"><div></div><div></div><div></div></div>
    @if ($search !== '')<p class="menu-search-label">Results for “{{ $search }}” · <a href="{{ route('menu.index', ['stall' => $stall->id]) }}">Clear search</a></p>@endif
    @forelse ($items->groupBy('category_id') as $categoryItems)
        <section class="experience-category-section" id="category-{{ $categoryItems->first()->category_id }}" data-category-section aria-labelledby="category-heading-{{ $categoryItems->first()->category_id }}">
            <h2 id="category-heading-{{ $categoryItems->first()->category_id }}">{{ $categoryItems->first()->category->name }}</h2>
            <div class="menu-products-grid">
                @foreach ($categoryItems as $item)
                    <article class="menu-product experience-product" data-dish="{{ json_encode(['id' => $item->id, 'name' => $item->name, 'description' => $item->description, 'price' => (int) str_replace('.', '', $item->price), 'maximum' => min(999, $stall->isTakingOrders() && $item->is_available ? $item->stock_qty : 0), 'image' =>  $item->imageUrl(), 'url' => route('cart.quantity', $item)]) }}">
                        <button class="food-photo-trigger" data-open-dish type="button" aria-label="View {{ $item->name }} details">@include('customer.food-image')</button>
                        <div class="food-card-copy"><h3><button type="button" data-open-dish>{{ $item->name }}</button></h3><p>{{ $item->description ?: $item->category->name }}</p>
                        <div class="food-card-footer"><strong>Php {{ number_format($item->price, 2) }}</strong>
                            @if ($stall->isTakingOrders() && $item->canOrder())
                                @php($itemQuantity = $cartLines->where('item_id', $item->id)->sum('quantity'))
                                <form method="post" action="{{ route('cart.store', $item) }}" class="menu-card-stepper" data-async-form data-cart-control data-cart-item="{{ $item->id }}" data-cart-url="{{ route('cart.quantity', $item) }}" data-cart-price="{{ (int) str_replace('.', '', $item->price) }}" data-cart-max="{{ min(999, $item->stock_qty) }}">
                                    @csrf
                                    <button type="submit" name="step" value="-1" aria-label="Decrease {{ $item->name }} quantity" @disabled($itemQuantity === 0)>−</button>
                                    <span data-cart-quantity aria-live="polite" aria-label="{{ $item->name }} quantity">{{ $itemQuantity }}</span>
                                    <button type="submit" name="step" value="1" aria-label="Increase {{ $item->name }} quantity" @disabled($itemQuantity >= min(999, $item->stock_qty))>+</button>
                                </form>
                            @else<button class="food-unavailable" type="button" data-open-dish>{{ $stall->isTakingOrders() ? 'Sold out' : 'Closed' }}</button>@endif
                        </div></div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        @include('customer.empty-state', ['emptyTitle' => $search !== '' || $categoryId ? 'Nothing on the menu matches yet' : 'The kitchen is getting ready', 'emptyDescription' => $search !== '' || $categoryId ? 'Try another craving or see the full menu.' : 'Explore another campus stall while this menu is being prepared.', 'emptyAction' => $search !== '' || $categoryId ? 'Clear Filters' : 'Browse Stalls', 'emptyUrl' => $search !== '' || $categoryId ? route('menu.index', ['stall' => $stall->id]) : route('stalls.index')])
    @endforelse
</div>
<div class="menu-cart-dock experience-basket-bar">
    <a href="{{ route('cart.index') }}" data-active-cart-link><span class="basket-count" data-header-cart-count>{{ $cartCount }}</span><span class="basket-total"><small data-cart-count-label>{{ $cartCount }} {{ Str::plural('item', $cartCount) }} in your order</small><strong data-cart-total>Php {{ number_format($cartSubtotal + $cartFee, 2) }}</strong></span><span class="basket-view">View Basket <img src="{{ asset('images/icons/menu-content-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain " decoding="async"></span></a>
</div>
@include('customer.desktop-cart-sidebar')
@include('customer.item-dialog')
@include('customer.cart-state', ['cartContext' => 'menu'])
<p class="customer-desktop-only desktop-menu-footnote">Availability is updated by your canteen staff.</p>




