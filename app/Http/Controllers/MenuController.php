<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Stall;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function stalls(Request $request): View
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';
        $stalls = Stall::query()->when($search !== '', function ($query) use ($search): void {
            $query->where(function ($query) use ($search): void {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereHas('menuItems', fn ($items) => $items->whereLike('name', "%{$search}%"));
            });
        })->orderBy('id')->get();

        $openCount = ($search === '' ? $stalls : Stall::where('is_active', true)->get())
            ->filter(fn (Stall $stall): bool => $stall->isTakingOrders())->count();

        return view('customer.stalls', [
            'stalls' => $stalls,
            'openCount' => $openCount,
            'search' => $search,
            'unreadCount' => $request->user()->pickupNotifications()->where('is_read', false)->count(),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'stall' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $categoryId = isset($filters['category']) ? (int) $filters['category'] : null;
        $search = $filters['search'] ?? '';
        $stall = Stall::findOrFail($filters['stall'] ?? Stall::DEFAULT_ID);
        $user->load(['cart.items.item', 'cart.stall']);
        $cart = $user->cart;
        $lines = $cart?->items ?? collect();
        $orderStall = $cart?->stall ?? $stall;
        $orderMethod = $cart?->fulfillment_method ?? 'pickup';
        $cartSubtotal = $lines->sum(fn (CartItem $line): int => $line->subtotalInCents()) / 100;
        $orderUpdates = $user->pickupNotifications()->where('is_read', false)
            ->where('message', 'not like', '% is ready%')->latest()->latest('id')->limit(8)->get();
        $notifications = $user->unreadPickupNotifications()->merge($orderUpdates)
            ->sortByDesc('id')->take(8)->load('order.receipt');

        return view('customer.menu', [
            'stall' => $stall,
            'orderStall' => $orderStall,
            'orderMethod' => $orderMethod,
            'cartLines' => $lines,
            'cartFee' => $orderMethod === 'delivery' ? $orderStall->delivery_fee : 0,
            'categories' => Category::whereHas('menuItems', fn ($items) => $items->where('stall_id', $stall->id))
                ->orderBy('id')->get(),
            'items' => $stall->menuItems()
                ->with('category:id,name')
                ->when($categoryId, fn ($items) => $items->where('category_id', $categoryId))
                ->when($search !== '', fn ($items) => $items->whereLike('name', "%{$search}%"))
                ->orderBy('category_id')->orderBy('name')->get(),
            'categoryId' => $categoryId,
            'search' => $search,
            'cartCount' => $lines->sum('quantity'),
            'cartSubtotal' => $cartSubtotal,
            'notifications' => $notifications,
            'unreadCount' => $user->pickupNotifications()->where('is_read', false)->count(),
        ]);
    }
}
