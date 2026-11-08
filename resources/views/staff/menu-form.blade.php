<dialog id="add-menu-item" aria-labelledby="add-menu-item-title" @if ($creatingItem) data-open-edit-dialog @endif class="edit-dialog">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 id="add-menu-item-title" class="text-xl font-bold text-maroon-800">Add menu item</h2>
            <p class="mt-1 text-sm text-stone-600">{{ $stall->name }}</p>
        </div>
        <button type="button" class="menu-icon !size-9" aria-label="Close add item dialog" data-close-edit-dialog>×</button>
    </div>
    @if (old('creating_item') && $errors->any())
        <ul role="alert" class="mt-4 list-disc rounded-xl bg-maroon-50 py-3 pr-3 pl-8 text-sm text-maroon-900">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    @include('staff.menu-fields', ['restoreInput' => (bool) old('creating_item')])
</dialog>
