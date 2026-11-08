<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDesktopWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_sidebar_uses_the_customers_cart_stall_while_browsing_another_menu(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $cartStall = Stall::factory()->create(['delivery_enabled' => true, 'delivery_fee' => '20.00']);
        $browsedStall = Stall::factory()->create();
        $item = MenuItem::factory()->for($cartStall)->create(['price' => '85.00']);
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('cart.service'), ['method' => 'delivery'])->assertSessionHasNoErrors();
        $response = $this->get(route('menu.index', ['stall' => $browsedStall->id]))->assertOk()->assertSee('Your basket');
        $this->assertSame($cartStall->id, $response->viewData('orderStall')->id);
        $this->assertSame('delivery', $response->viewData('orderMethod'));
        $this->assertEquals(85, $response->viewData('cartSubtotal'));
        $this->assertEquals(20, $response->viewData('cartFee'));
        $this->assertSame($item->id, $response->viewData('cartLines')->sole()->item_id);
    }

    public function test_combined_orders_screen_scopes_active_orders_history_and_notifications(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $active = Order::factory()->for($customer, 'customer')->create();
        $november = Order::factory()->for($customer, 'customer')->create(['status' => 'completed', 'order_date' => '2026-10-31 16:00:00']);
        Order::factory()->for($customer, 'customer')->create(['status' => 'completed', 'order_date' => '2026-11-30 16:00:00']);
        $foreign = Order::factory()->create(['status' => 'ready']);
        $customer->pickupNotifications()->create(['message' => 'Own order update']);
        $response = $this->actingAs($customer)->get(route('orders.index', ['month' => '2026-11']))->assertOk()->assertSee('My Orders &amp; notifications', false)->assertDontSee($foreign->displayReference());
        $this->assertSame($active->id, $response->viewData('activeOrders')->sole()->id);
        $this->assertSame($november->id, $response->viewData('pastOrders')->sole()->id);
        $this->assertSame(1, $response->viewData('unreadCount'));
        $this->get(route('orders.index', ['month' => 'invalid']))->assertSessionHasErrors('month');
    }

    public function test_desktop_checkout_and_tracking_keep_payment_and_receipt_rules(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))->assertOk()->assertSee('Review &amp; checkout', false)->assertSee('desktop-checkout-form');
        $order = Order::factory()->for($customer, 'customer')->create(['status' => 'ready']);
        $this->get(route('orders.show', $order->reference_number))->assertOk()->assertSee('Unpaid')->assertSee('Available after payment');
        $this->get(route('orders.receipt', $order->reference_number))->assertNotFound();
    }
}
