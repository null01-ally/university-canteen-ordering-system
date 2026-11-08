<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Stall;
use App\Services\CancelCanteenOrder;
use App\Services\LockCanteenAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function history(Request $request): View
    {
        $request->query->set('history', '1');

        return $this->index($request);
    }

    public function track(Request $request): RedirectResponse
    {
        $order = $request->user()->orders()->whereIn('status', ['pending', 'preparing', 'ready'])->latest('order_date')->latest('id')->first()
            ?? $request->user()->orders()->latest('order_date')->latest('id')->first();

        return $order ? to_route('orders.show', $order->reference_number) : to_route('orders.index');
    }

    public function reorder(Request $request, Order $order, LockCanteenAccount $access): RedirectResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $order, $access): void {
            $customer = $access->handle($request->user(), 'customer');
            $source = $customer->orders()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($source->status !== 'completed') {
                throw ValidationException::withMessages(['order' => 'Only completed orders can be ordered again.']);
            }
            $cart = $customer->cart()->firstOrCreate();
            if ($cart->items()->exists()) {
                throw ValidationException::withMessages(['cart' => 'Your basket already has items. Finish or clear it before ordering again.']);
            }
            $stall = Stall::whereKey($source->stall_id)->lockForUpdate()->firstOrFail();
            $method = $stall->accepts($source->fulfillment_method) ? $source->fulfillment_method : 'pickup';
            if (! $stall->accepts($method)) {
                throw ValidationException::withMessages(['order' => 'This stall is not accepting orders right now.']);
            }
            $lines = $source->lines()->get();
            $items = MenuItem::whereKey($lines->pluck('item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'There are no items to reorder.']);
            }
            foreach ($lines->groupBy('item_id') as $itemId => $group) {
                $quantity = $group->sum('quantity');
                $item = $items->get($itemId);
                if ($quantity > 999 || ! $item?->canOrder($quantity) || $item->stall_id !== $stall->id) {
                    throw ValidationException::withMessages(['order' => 'Some items are no longer available. Browse the stall menu to choose an alternative.']);
                }
                $cart->items()->create(['item_id' => $itemId, 'quantity' => $quantity, 'instructions' => $group->first()->instructions]);
            }
            $cart->update(['stall_id' => $stall->id, 'fulfillment_method' => $method]);
        }, 3);

        return to_route('cart.index')->with('status', 'Added to your basket at current menu prices. Review before checkout.');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:completed,cancelled'],
            'history' => ['nullable', 'boolean'],
            'month' => ['nullable', 'date_format:Y-m'],
            'unread' => ['nullable', 'boolean'],
        ]);
        $past = $request->user()->orders()->with(['stall', 'receipt'])->withSum('lines', 'quantity')->whereIn('status', ['completed', 'cancelled'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        if (! empty($filters['month'])) {
            $start = Carbon::parse($filters['month'].'-01', config('canteen.timezone'))->startOfDay();
            $past->where('order_date', '>=', $start->copy()->utc())->where('order_date', '<', $start->copy()->addMonth()->utc());
        }
        $recentUpdates = $request->user()->pickupNotifications()->with('order.receipt')
            ->when($request->boolean('unread'), fn ($query) => $query->where('is_read', false))->latest()->latest('id')->limit(20)->get();
        $legacyReferences = $recentUpdates->map(fn (Notification $notification) => $notification->order_id === null ? $notification->readyOrderReference() : null)->filter();
        $readyReferences = $legacyReferences->isEmpty() ? collect() : $request->user()->orders()->where('status', 'ready')->whereIn('reference_number', $legacyReferences)
            ->pluck('reference_number')->map(fn (string $reference) => strtolower($reference))->flip();
        $recentUpdates = $recentUpdates->filter(function (Notification $notification) use ($readyReferences): bool {
            if (! str_contains($notification->message, 'is ready')) {
                return true;
            }
            if ($notification->order_id !== null) {
                return $notification->order?->status === 'ready';
            }
            $reference = $notification->readyOrderReference();

            return $reference === null || $readyReferences->has($reference);
        })->take(6);

        return view('customer.orders', [
            'orders' => $request->user()->orders()->with(['stall', 'receipt'])->withSum('lines', 'quantity')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($request->boolean('history'), fn ($query) => $query->whereIn('status', ['completed', 'cancelled']))
                ->latest('order_date')->latest('id')->paginate(10)->withQueryString(),
            'notifications' => $request->user()->unreadPickupNotifications(),
            'pastOrders' => $past->latest('order_date')->latest('id')->paginate(5, ['*'], 'past_page')->withQueryString(),
            'activeOrders' => $request->user()->orders()->with('stall')->withSum('lines', 'quantity')->whereIn('status', ['pending', 'preparing', 'ready'])
                ->latest('order_date')->latest('id')->paginate(5, ['*'], 'active_page')->withQueryString(),
            'recentUpdates' => $recentUpdates,
            'unreadCount' => $request->user()->pickupNotifications()->where('is_read', false)->count(),
        ]);
    }

    public function notifications(Request $request): View
    {
        $request->validate(['unread' => ['nullable', 'boolean']]);

        return view('customer.notification-center', [
            'updates' => $request->user()->pickupNotifications()->with('order.receipt')
                ->when($request->boolean('unread'), fn ($query) => $query->where('is_read', false))
                ->latest()->latest('id')->paginate(15)->withQueryString(),
            'unreadCount' => $request->user()->pickupNotifications()->where('is_read', false)->count(),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->pickupNotifications()->where('is_read', false)->update(['is_read' => true]);

        return back();
    }

    public function receipt(Request $request, Order $order): View
    {
        abort_unless($order->customer_id === $request->user()->id, 404);
        $order->loadMissing('receipt');
        abort_unless($order->status === 'completed' && $order->receipt !== null, 404);

        return view('customer.receipt', ['order' => $order->loadMissing(['lines', 'stall'])]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->customer_id === $request->user()->id, 404);

        return view('customer.order', ['order' => $order->load(['lines.item', 'receipt', 'stall'])]);
    }

    public function read(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->customer_id === $request->user()->id, 404);

        $notification->update(['is_read' => true]);

        return back();
    }

    public function cancel(Request $request, Order $order, CancelCanteenOrder $cancellation): RedirectResponse
    {
        $cancellation->handle($order, $request->user(), null);

        return to_route('orders.show', $order->reference_number)->with('status', 'Order cancelled.');
    }
}
