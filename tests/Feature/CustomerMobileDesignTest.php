<?php

namespace Tests\Feature;

use App\Models\DigitalReceipt;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMobileDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_center_filters_and_marks_only_own_updates_read(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $own = $customer->pickupNotifications()->create(['message' => 'Your order is ready']);
        $foreign = $other->pickupNotifications()->create(['message' => 'Private other order']);
        $this->actingAs($customer)->get(route('notifications.index'))->assertOk()->assertSee($own->message)->assertDontSee($foreign->message);
        $this->patch(route('notifications.read', $foreign))->assertNotFound();
        $this->patch(route('notifications.read-all'))->assertSessionHasNoErrors();
        $this->assertTrue($own->fresh()->is_read);
        $this->assertFalse($foreign->fresh()->is_read);
        $this->get(route('notifications.index', ['unread' => 1]))->assertOk()->assertDontSee($own->message);
    }

    public function test_history_filters_and_receipts_are_scoped_to_the_customer(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $completed = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);
        $pending = Order::factory()->for($customer, 'customer')->create();
        DigitalReceipt::factory()->create(['order_id' => $completed->id]);
        $this->actingAs($customer)->get(route('orders.index', ['history' => 1]))->assertOk()->assertSee('Past Orders')->assertSee($completed->displayReference())->assertDontSee($pending->displayReference());
        $this->get(route('orders.receipt', $completed->reference_number))->assertOk()->assertSee('Cash paid');
        $this->get(route('orders.receipt', $pending->reference_number))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('orders.receipt', $completed->reference_number))->assertNotFound();
    }
}
