<?php

namespace Tests\Feature;

use App\Models\DigitalReceipt;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanteenSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_references_are_unique_and_lines_preserve_item_history(): void
    {
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['name' => 'Rice Bowl']);
        $order = Order::factory()->for($customer, 'customer')->create();
        $line = OrderLine::factory()->for($order)->for($item, 'item')->create();
        $anotherOrder = Order::factory()->for($customer, 'customer')->create();

        $item->update(['name' => 'Chicken Bowl', 'price' => '75.00']);
        $item->delete();

        $this->assertSame('customer', $customer->user_type);
        $this->assertTrue($customer->is_active);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', strtoupper($order->reference_number));
        $this->assertNotSame($order->reference_number, $anotherOrder->reference_number);
        $this->assertCount(2, $customer->orders);
        $this->assertSame('Rice Bowl', $line->fresh()->item_name);
        $this->assertSame('65.00', $line->fresh()->unit_price);
        $this->assertTrue($line->item->trashed());
    }

    public function test_each_order_can_have_only_one_receipt(): void
    {
        $order = Order::factory()->create(['status' => 'completed']);
        DigitalReceipt::factory()->for($order)->create();

        $this->expectException(QueryException::class);

        DigitalReceipt::factory()->for($order)->create();
    }
}
