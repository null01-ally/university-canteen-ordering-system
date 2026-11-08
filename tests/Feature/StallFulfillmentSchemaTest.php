<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StallFulfillmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_defaults_keep_existing_order_creation_compatible(): void
    {
        $order = Order::factory()->create();
        $item = MenuItem::factory()->create();
        $cart = Cart::factory()->create();

        $this->assertSame('Stall No. 1', $order->stall->name);
        $this->assertTrue($order->stall->is($item->stall));
        $this->assertTrue($order->stall->is($cart->stall));
        $this->assertSame('pickup', $order->fresh()->fulfillment_method);
        $this->assertSame('pickup', $cart->fresh()->fulfillment_method);
        $this->assertSame('0.00', $order->fresh()->delivery_fee);
        $this->assertDatabaseHas('canteen_settings', ['id' => 1, 'delivery_enabled' => false]);
    }

    public function test_delivery_order_keeps_its_fee_after_the_stall_rate_changes(): void
    {
        $stall = Stall::factory()->create(['delivery_fee' => '15.00']);
        $order = Order::factory()->for($stall)->create([
            'fulfillment_method' => 'delivery',
            'contact_name' => 'Campus Customer',
            'contact_phone' => '09123456789',
            'delivery_location' => 'Library entrance',
            'notes' => 'Meet at the front desk.',
            'delivery_fee' => $stall->delivery_fee,
        ]);

        $stall->update(['delivery_fee' => '20.00']);

        $this->assertSame('delivery', $stall->orders()->sole()->fulfillment_method);
        $this->assertSame('Library entrance', $order->fresh()->delivery_location);
        $this->assertSame('15.00', $order->fresh()->delivery_fee);
    }
}
