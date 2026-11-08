<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelCanteenOrder
{
    public function __construct(private LockCanteenAccount $access) {}

    public function handle(Order $order, User $actor, ?string $reason): void
    {
        DB::transaction(function () use ($order, $actor, $reason): void {
            abort_unless(in_array($actor->user_type, ['customer', 'staff'], true), 403);
            $actor = $this->access->handle($actor, $actor->user_type, $actor->user_type === 'staff' ? $order->stall_id : null);
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $customer = $actor->user_type === 'customer' && $actor->id === $order->customer_id;
            $staff = $actor->user_type === 'staff' && $actor->stall_id === $order->stall_id;
            abort_unless($customer || $staff, 404);

            if ($order->status === 'cancelled') {
                return;
            }
            if (! in_array($order->status, $customer ? ['pending'] : ['pending', 'preparing', 'ready'], true)) {
                throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled.']);
            }

            $quantities = $order->lines()->get()->groupBy('item_id')
                ->map(fn ($lines): int => $lines->sum('quantity'));
            $items = MenuItem::withTrashed()->whereKey($quantities->keys()->all())->orderBy('id')->lockForUpdate()->get();

            foreach ($items as $item) {
                $quantity = $quantities->get($item->id);
                if ($item->stock_qty + $quantity > 4294967295) {
                    throw ValidationException::withMessages(['order' => 'Inventory is at its storage limit. Ask staff to review stock before cancelling.']);
                }
                $item->increment('stock_qty', $quantity);
            }

            $order->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();
            $order->customer->pickupNotifications()->where('order_id', $order->id)->update(['is_read' => true]);

            if ($staff || $customer) {
                $reference = $order->displayReference();
                $order->customer->pickupNotifications()->create([
                    'order_id' => $order->id,
                    'message' => "Order {$reference} was cancelled. No payment was collected.",
                ]);
            }
        }, 3);
    }
}
