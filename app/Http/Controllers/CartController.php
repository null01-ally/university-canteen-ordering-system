<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        return $this->cartView($request, 'customer.cart');
    }

    public function review(Request $request): View
    {
        return $this->cartView($request, 'customer.checkout');
    }

    private function cartView(Request $request, string $view): View
    {
        $request->user()->load(['cart.items' => fn ($items) => $items->with('item')->orderBy('id'), 'cart.stall']);
        $cart = $request->user()->cart;
        $lines = $cart?->items ?? new Collection;
        if ($view === 'customer.checkout' && $lines->isEmpty()) {
            return view('customer.empty-checkout');
        }
        $stall = $cart?->stall ?? Stall::findOrFail(Stall::DEFAULT_ID);
        $method = $cart?->fulfillment_method ?? 'pickup';
        $subtotal = $this->totalInCents($lines);
        $fee = $method === 'delivery' ? (int) str_replace('.', '', $stall->delivery_fee) : 0;

        return view($view, [
            'lines' => $lines,
            'stall' => $stall,
            'method' => $method,
            'subtotal' => $subtotal / 100,
            'fee' => $fee / 100,
            'total' => ($subtotal + $fee) / 100,
            'snapshot' => $this->snapshot($lines, $stall->id, $method, $fee),
            'deliveryEnabled' => $stall->accepts('delivery'),
            'pickupEnabled' => $stall->accepts('pickup'),
            'canCheckout' => $stall->accepts($method) && $lines->isNotEmpty() && $lines->every(
                fn (CartItem $line): bool => $line->item?->canOrder($line->quantity) ?? false,
            ),
        ]);
    }

    public function service(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'in:pickup,delivery'],
            'return_to' => ['nullable', 'in:checkout,cart'],
        ]);
        $method = $data['method'];

        DB::transaction(function () use ($request, $method): void {
            $cart = $this->lockCustomer($request)->cart()->firstOrCreate();
            $stall = Stall::whereKey($cart->stall_id)->lockForUpdate()->firstOrFail();
            if (! $stall->accepts($method)) {
                throw ValidationException::withMessages(['method' => 'The selected service is unavailable at this stall.']);
            }
            $cart->update(['fulfillment_method' => $method]);
        }, 3);

        return to_route(($data['return_to'] ?? 'cart') === 'checkout' ? 'checkout.index' : 'cart.index');
    }

    public function store(Request $request, MenuItem $item): RedirectResponse
    {
        $quantity = $this->quantity($request);

        DB::transaction(function () use ($request, $item, $quantity): void {
            $customer = $this->lockCustomer($request);
            $cart = $customer->cart()->firstOrCreate();
            $stall = Stall::whereKey($item->stall_id)->lockForUpdate()->firstOrFail();
            if (! $stall->isTakingOrders()) {
                throw ValidationException::withMessages(['cart' => 'This stall is currently closed.']);
            }
            $hasItems = $cart->items()->exists();
            if ($hasItems && $cart->stall_id !== $item->stall_id) {
                throw ValidationException::withMessages(['cart' => 'Finish or clear your order before adding items from another stall.']);
            }
            if (! $hasItems) {
                $cart->update([
                    'stall_id' => $stall->id,
                    'fulfillment_method' => $stall->accepts($cart->fulfillment_method)
                        ? $cart->fulfillment_method
                        : ($stall->accepts('pickup') ? 'pickup' : 'delivery'),
                ]);
            }
            $line = $cart->items()->firstOrNew(['item_id' => $item->id]);
            $this->saveQuantity($line, $item->id, $line->quantity + $quantity);
        }, 3);

        return back()->with('status', 'Added to your cart.');
    }

    public function setQuantity(Request $request, MenuItem $item): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'context' => ['required', 'in:menu,cart'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $quantity = (int) $data['quantity'];
        DB::transaction(function () use ($request, $item, $quantity, $data): void {
            $customer = $this->lockCustomer($request);
            $cart = $customer->cart()->firstOrCreate();
            $line = $cart->items()->where('item_id', $item->id)->first();
            if ($quantity === 0) {
                $line?->delete();

                return;
            }
            if ($line && $quantity <= $line->quantity) {
                $line->fill(['quantity' => $quantity]);
                if (array_key_exists('instructions', $data)) {
                    $line->instructions = $data['instructions'];
                }
                $line->save();

                return;
            }
            $stall = Stall::whereKey($item->stall_id)->lockForUpdate()->firstOrFail();
            if ($item->trashed() || ! $stall->isTakingOrders()) {
                throw ValidationException::withMessages(['cart' => 'This item or stall is currently unavailable.']);
            }
            $hasItems = $cart->items()->exists();
            if ($hasItems && $cart->stall_id !== $item->stall_id) {
                throw ValidationException::withMessages(['cart' => 'Finish or clear your order before adding items from another stall.']);
            }
            if (! $hasItems) {
                $cart->update([
                    'stall_id' => $stall->id,
                    'fulfillment_method' => $stall->accepts($cart->fulfillment_method)
                        ? $cart->fulfillment_method : ($stall->accepts('pickup') ? 'pickup' : 'delivery'),
                ]);
            }
            $line ??= $cart->items()->make(['item_id' => $item->id]);
            if (array_key_exists('instructions', $data)) {
                $line->instructions = $data['instructions'];
            }
            $this->saveQuantity($line, $item->id, $quantity);
        }, 3);

        $request->user()->load(['cart.items.item', 'cart.stall']);
        $cart = $request->user()->cart;
        $lines = $cart->items;
        $fee = $cart->fulfillment_method === 'delivery' ? (int) str_replace('.', '', $cart->stall->delivery_fee) : 0;
        $summary = [
            'items' => $lines->map(fn (CartItem $line): array => [
                'id' => $line->item_id, 'quantity' => $line->quantity,
                'instructions' => $line->instructions,
                'unit_cents' => (int) str_replace('.', '', $line->item?->price ?? '0.00'),
                'maximum' => min(999, $line->item?->is_available ? $line->item->stock_qty : 0),
            ])->values(),
            'fee_cents' => $fee,
        ];
        $sidebar = $data['context'] === 'menu' ? view('customer.desktop-cart-sidebar', [
            'cartLines' => $lines, 'cartCount' => $lines->sum('quantity'),
            'cartSubtotal' => $this->totalInCents($lines) / 100, 'cartFee' => $fee / 100,
            'orderStall' => $cart->stall, 'orderMethod' => $cart->fulfillment_method,
        ])->render() : null;

        return response()->json([
            'cart' => $summary, 'sidebar' => $sidebar,
            'page' => $data['context'] === 'cart' ? $this->cartView($request, 'customer.cart')->render() : null,
        ]);
    }

    public function update(Request $request, CartItem $line): RedirectResponse
    {
        $data = $request->validate([
            'step' => ['required_without:quantity', 'prohibits:quantity', 'integer', 'in:-1,1'],
            'quantity' => ['required_without:step', 'integer', 'min:1', 'max:999'],
        ]);

        DB::transaction(function () use ($request, $line, $data): void {
            $line = $this->ownedLine($this->lockCustomer($request), $line);
            $quantity = isset($data['step']) ? $line->quantity + (int) $data['step'] : (int) $data['quantity'];

            if ($quantity === 0) {
                $line->delete();
            } elseif ($quantity < $line->quantity) {
                $line->update(['quantity' => $quantity]);
            } elseif ($quantity > $line->quantity) {
                $this->saveQuantity($line, $line->item_id, $quantity);
            }
        }, 3);

        return to_route('cart.index')->with('status', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $line): RedirectResponse
    {
        DB::transaction(function () use ($request, $line): void {
            $this->ownedLine($this->lockCustomer($request), $line)->delete();
        }, 3);

        return to_route('cart.index')->with('status', 'Item removed.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'snapshot' => ['required', 'string', 'size:64'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/\A(?=(?:\D*\d){7})[0-9+() -]{7,32}\z/'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['sometimes', 'required', 'in:cash_at_pickup,cash_on_delivery'],
        ]);

        $order = DB::transaction(function () use ($request, $data) {
            $customer = $this->lockCustomer($request);
            $cart = $customer->cart;
            $lines = $cart?->items()->orderBy('id')->get() ?? new Collection;
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $stall = Stall::whereKey($cart->stall_id)->lockForUpdate()->firstOrFail();
            $method = $cart->fulfillment_method;
            $delivery = $method === 'delivery';
            $paymentMethod = $delivery ? 'cash_on_delivery' : 'cash_at_pickup';
            if (isset($data['payment_method']) && $data['payment_method'] !== $paymentMethod) {
                throw ValidationException::withMessages(['payment_method' => 'Choose the cash payment option for the selected service.']);
            }
            if (! $stall->accepts($method)) {
                throw ValidationException::withMessages(['cart' => 'The selected service is unavailable. Review your cart and try again.']);
            }
            if ($delivery && (empty($data['contact_phone']) || empty($data['delivery_location']))) {
                throw ValidationException::withMessages(['delivery_location' => 'Enter a mobile number and an on-campus delivery location.']);
            }
            $fee = $delivery ? (int) str_replace('.', '', $stall->delivery_fee) : 0;

            $items = MenuItem::whereKey($lines->pluck('item_id')->all())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $line) {
                $item = $items->get($line->item_id);
                if (! $item?->canOrder($line->quantity) || $item->stall_id !== $stall->id) {
                    throw ValidationException::withMessages(['cart' => 'Some items are unavailable in the selected quantities. Update your cart and try again.']);
                }

                $line->setRelation('item', $item);
            }

            if (! hash_equals($this->snapshot($lines, $stall->id, $method, $fee), $data['snapshot'])) {
                throw ValidationException::withMessages(['cart' => 'Your cart or item prices changed. Review the updated total before placing your order.']);
            }

            $total = $this->totalInCents($lines) + $fee;
            if ($total > 999999999999) {
                throw ValidationException::withMessages(['cart' => 'Your order exceeds the maximum total. Reduce the quantities and try again.']);
            }

            $order = $customer->orders()->create([
                'order_date' => now(),
                'total_amount' => number_format($total / 100, 2, '.', ''),
                'stall_id' => $stall->id,
                'fulfillment_method' => $method,
                'payment_method' => $paymentMethod,
                'contact_name' => $data['contact_name'] ?? $customer->name,
                'contact_phone' => $data['contact_phone'] ?? null,
                'delivery_location' => $delivery ? $data['delivery_location'] : null,
                'notes' => $data['notes'] ?? null,
                'delivery_fee' => number_format($fee / 100, 2, '.', ''),
            ]);

            foreach ($lines as $line) {
                $item = $line->item;
                $order->lines()->create([
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $item->price,
                    'instructions' => $line->instructions,
                ]);
                $item->decrement('stock_qty', $line->quantity);
            }

            $cart->items()->delete();
            $customer->pickupNotifications()->create([
                'order_id' => $order->id,
                'message' => 'Order '.$order->displayReference().' was received by '.Str::limit($stall->name, 100).'. Your meal will be prepared shortly.',
            ]);

            return $order;
        }, 3);

        return to_route('orders.show', $order->reference_number);
    }

    private function quantity(Request $request): int
    {
        return (int) $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:999']])['quantity'];
    }

    private function lockCustomer(Request $request): User
    {
        $customer = User::query()->lockForUpdate()->findOrFail($request->user()->id);
        abort_unless($customer->is_active && $customer->user_type === 'customer', 403);

        return $customer;
    }

    private function ownedLine(User $customer, CartItem $line): CartItem
    {
        abort_unless($customer->cart instanceof Cart, 404);

        return $customer->cart->items()->findOrFail($line->id);
    }

    private function saveQuantity(CartItem $line, int $itemId, int $quantity): void
    {
        $item = MenuItem::query()->lockForUpdate()->find($itemId);
        if ($quantity > 999 || ! $item?->canOrder($quantity)) {
            throw ValidationException::withMessages(['quantity' => 'That quantity is unavailable. Check the menu or choose fewer items.']);
        }

        $line->quantity = $quantity;
        $line->save();
    }

    /** @param Collection<int, CartItem> $lines */
    private function snapshot(Collection $lines, int $stallId, string $method, int $fee): string
    {
        return hash('sha256', json_encode([$stallId, $method, $fee, $lines->map(
            fn (CartItem $line): array => [$line->id, $line->quantity, $line->item?->price, $line->instructions],
        )], JSON_THROW_ON_ERROR));
    }

    /** @param Collection<int, CartItem> $lines */
    private function totalInCents(Collection $lines): int
    {
        return $lines->sum(fn (CartItem $line): int => $line->subtotalInCents());
    }
}
