@extends('layout')
@section('title', 'Menu & inventory')
@section('content')
<div class="kds-heading"><h1 class="workspace-heading">Menu &amp; inventory</h1><a class="button-primary" href="{{ route('staff.menu.create') }}" data-edit-dialog="add-menu-item" aria-haspopup="dialog">+ Add item</a></div>
<div class="staff-inventory-summary" aria-label="Inventory summary">
    <span>All items <strong>{{ $total }}</strong></span><span>Available <strong>{{ $available }}</strong></span><span>Sold out <strong>{{ $total - $available }}</strong></span>
</div>
<form action="{{ route('staff.inventory.index') }}" class="staff-inventory-filters">
    <input type="search" name="search" class="field" placeholder="Search menu items..." aria-label="Search menu items" value="{{ request('search') }}" maxlength="100">
    <select name="category" class="field" aria-label="Filter category"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) $category->id === request('category'))>{{ $category->name }}</option>@endforeach</select>
    <button class="button-primary">Apply filters</button>
    @if (request()->filled('search') || request()->filled('category'))<a href="{{ route('staff.inventory.index') }}" class="text-link">Clear</a>@endif
    <span class="text-xs text-stone-600">{{ $items->total() }} results</span>
</form>
<div class="kds-inventory">
@forelse ($items as $item)
    <article class="kds-product">
        @include('menu-image', ['menuImageItem' => $item, 'menuImageClass' => 'aspect-[2] w-full object-cover'])
        <div class="kds-product-body"><h2>{{ $item->name }}</h2><p class="kds-price">Php {{ number_format($item->price, 2) }}</p>
        <form method="post" action="{{ route('staff.menu.availability', $item) }}" data-kds-toggle>
            @csrf @method('PATCH')
            <button type="submit" name="is_available" value="{{ (int) ! $item->canOrder() }}" class="kds-toggle kds-stock-toggle" role="switch" aria-label="Availability for {{ $item->name }}" aria-checked="{{ $item->canOrder() ? 'true' : 'false' }}" data-on-label="Available" data-off-label="Sold Out" @disabled($item->stock_qty === 0)>
                <span data-toggle-label>{{ $item->canOrder() ? 'Available' : 'Sold Out' }}</span><span class="kds-switch-track" aria-hidden="true"><span></span></span>
            </button><p class="kds-feedback" role="status"></p>
        </form>
        @if ($item->stock_qty === 0)<p class="text-sm text-red-800 mt-2">Restock to make available.</p>@endif
        <div class="mt-1 flex flex-wrap items-center justify-between gap-x-3">
            <a href="{{ route('staff.menu.edit', $item) }}" class="kds-edit" data-edit-dialog="edit-menu-{{ $item->id }}" aria-label="Edit {{ $item->name }}">Edit item</a>
            <form method="post" action="{{ route('staff.menu.destroy', $item) }}" data-confirm-item-removal>
                @csrf @method('DELETE')
                <button type="submit" class="min-h-11 cursor-pointer rounded px-1 text-xs font-semibold text-red-700 hover:text-red-900 focus-visible:outline-2 focus-visible:outline-maroon-800" aria-label="Remove {{ $item->name }}">Remove this item</button>
            </form>
        </div></div>
    </article>
@empty
    <p class="kds-empty">No items match. Clear your filters or add a menu item.</p>
@endforelse
</div>
@foreach ($items as $item) @include('staff.menu-dialog') @endforeach
@include('staff.menu-form', ['item' => $newItem])
@if ($editingItem && ! $items->getCollection()->contains($editingItem)) @include('staff.menu-dialog', ['item' => $editingItem]) @endif
<div class="mt-6">{{ $items->links() }}</div>
@endsection



