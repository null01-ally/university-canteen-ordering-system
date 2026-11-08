<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Services\LockCanteenAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffMenuController extends Controller
{
    public function __construct(private LockCanteenAccount $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'min:1'],
            'edit' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = $filters['search'] ?? '';
        $category = $filters['category'] ?? null;
        $stall = $request->user()->stall;
        $creatingItem = $request->old('creating_item') || $request->boolean('create');
        $editingId = $request->old('editing_item', $filters['edit'] ?? null);
        $inventory = $stall->menuItems();
        $counts = (clone $inventory)->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_available = 1 AND stock_qty > 0 THEN 1 ELSE 0 END), 0) AS available')->toBase()->first();
        $items = (clone $inventory)
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"))
            ->when($category, fn ($query) => $query->where('category_id', $category));

        return view('staff.menu', [
            'items' => $items->orderBy('name')->orderBy('id')->paginate(12)->appends($request->only(['search', 'category'])),
            'categories' => Category::orderBy('name')->get(),
            'total' => $counts->total,
            'available' => $counts->available,
            'stall' => $stall,
            'editingItem' => ! $creatingItem && is_numeric($editingId) ? $stall->menuItems()->find((int) $editingId) : null,
            'creatingItem' => $creatingItem,
            'newItem' => new MenuItem,
        ]);
    }

    public function create(): RedirectResponse
    {
        return to_route('staff.menu.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['creating_item' => true]);
        $item = new MenuItem;
        $item->stall_id = $request->user()->stall_id;
        $this->saveItem($item, $request);

        return to_route('staff.menu.index')->with('status', 'Menu item created.');
    }

    public function edit(Request $request, MenuItem $menu): RedirectResponse
    {
        abort_unless($menu->stall_id === $request->user()->stall_id, 404);

        return to_route('staff.menu.index', ['edit' => $menu->id]);
    }

    public function update(Request $request, MenuItem $menu): RedirectResponse
    {
        abort_unless($menu->stall_id === $request->user()->stall_id, 404);
        $request->merge(['editing_item' => $menu->id]);
        $stock = $request->validate(['original_stock_qty' => ['required', 'integer']]);

        $this->saveItem($menu, $request, (int) $stock['original_stock_qty']);

        return to_route('staff.menu.index')->with('status', 'Menu item updated.');
    }

    public function availability(Request $request, MenuItem $menu): RedirectResponse|JsonResponse
    {
        abort_unless($menu->stall_id === $request->user()->stall_id, 404);
        $data = $request->validate(['is_available' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $menu, $data): void {
            $actor = $this->access->handle($request->user(), 'staff', $menu->stall_id);
            $item = MenuItem::whereKey($menu->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->stall_id === $actor->stall_id, 404);
            if ($data['is_available'] && $item->stock_qty < 1) {
                throw ValidationException::withMessages(['is_available' => 'Restock this item before making it available.']);
            }
            $item->update($data);
        }, 3);

        if ($request->expectsJson()) {
            return response()->json(['state' => ['is_available' => $menu->fresh()->canOrder()]]);
        }

        return back()->with('status', 'Availability updated.');
    }

    public function destroy(Request $request, MenuItem $menu): RedirectResponse
    {
        abort_unless($menu->stall_id === $request->user()->stall_id, 404);
        DB::transaction(function () use ($request, $menu): void {
            $actor = $this->access->handle($request->user(), 'staff', $menu->stall_id);
            $item = MenuItem::whereKey($menu->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->stall_id === $actor->stall_id, 404);
            $item->delete();
        }, 3);

        return to_route('staff.menu.index')->with('status', 'Menu item removed.');
    }

    private function saveItem(MenuItem $item, Request $request, ?int $expectedStock = null): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'stock_qty' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_available' => ['required', 'boolean'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ]);

        unset($data['photo']);

        $photo = $request->file('photo')?->store('menu', 'public');
        if ($photo === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Try again.']);
        }
        if ($photo !== null) {
            $data['image_path'] = $photo;
        }

        $previousImage = null;
        try {
            DB::transaction(function () use ($item, $request, $data, $expectedStock, &$previousImage): void {
                $actor = $this->access->handle($request->user(), 'staff', $item->stall_id);
                $lockedItem = $item->exists ? MenuItem::whereKey($item->id)->lockForUpdate()->firstOrFail() : new MenuItem(['stall_id' => $actor->stall_id]);
                abort_unless($lockedItem->stall_id === $actor->stall_id, 404);
                if ($expectedStock !== null && $lockedItem->stock_qty !== $expectedStock) {
                    throw ValidationException::withMessages(['stock_qty' => 'Stock changed while you were editing. Reload this page before saving.']);
                }
                $previousImage = $lockedItem->image_path;
                $lockedItem->fill($data)->save();
            }, 3);
        } catch (\Throwable $exception) {
            if ($photo !== null) {
                Storage::disk('public')->delete($photo);
            }

            throw $exception;
        }
        if ($photo !== null && $previousImage !== null && str_starts_with($previousImage, 'menu/')) {
            Storage::disk('public')->delete($previousImage);
        }
    }
}
