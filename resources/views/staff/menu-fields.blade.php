<form method="post" enctype="multipart/form-data" action="{{ $item->exists ? route('staff.menu.update', $item) : route('staff.menu.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2" data-async-form>
    @csrf
    @if ($item->exists)
        @method('PATCH')
        <input type="hidden" name="original_stock_qty" value="{{ $restoreInput ? old('original_stock_qty', $item->stock_qty) : $item->stock_qty }}">
    @endif
    <label class="field-label">Item name
        <input class="field" name="name" value="{{ $restoreInput ? old('name', $item->name) : $item->name }}" maxlength="255" required>
    </label>
    <div>
        <label class="field-label" for="menu-category-{{ $item->id ?? 'new' }}">Category</label>
        <select class="field" id="menu-category-{{ $item->id ?? 'new' }}" name="category_id" required>
            <option value="">Select a category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $category->id === (string) ($restoreInput ? old('category_id', $item->category_id) : $item->category_id))>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    @if ($categories->isEmpty())
        <p class="text-sm text-maroon-800 sm:col-span-2">No categories are available. Ask an administrator to set up menu categories.</p>
    @endif
    <label class="field-label">Price (PHP)
        <input class="field" name="price" type="number" min="0.01" max="99999999.99" step="0.01" value="{{ $restoreInput ? old('price', $item->price) : $item->price }}" required>
    </label>
    <label class="field-label">Stock quantity
        <input class="field" name="stock_qty" type="number" min="0" max="4294967295" value="{{ $restoreInput ? old('stock_qty', $item->stock_qty) : $item->stock_qty }}" required>
    </label>
    <label class="field-label sm:col-span-2">Description
        <textarea class="field min-h-20" name="description" maxlength="2000" required>{{ $restoreInput ? old('description', $item->description) : $item->description }}</textarea>
    </label>
    <label class="field-label sm:col-span-2">Menu photo
        <input class="field" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
    </label>
    <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2">
        <input type="hidden" name="is_available" value="0">
        <input class="size-4 accent-maroon-800" type="checkbox" name="is_available" value="1" @checked($restoreInput ? old('is_available', $item->is_available) : $item->is_available)>
        Allow sales while stock is available
    </label>
    <div class="flex flex-wrap justify-end gap-2 sm:col-span-2">
        <button type="button" class="design-secondary" data-close-edit-dialog>Cancel</button>
        <button class="design-primary" @disabled($categories->isEmpty())>Save menu item</button>
    </div>
</form>
