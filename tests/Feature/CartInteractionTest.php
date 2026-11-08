<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_quantity_saves_are_idempotent_and_return_only_cart_updates_on_the_menu(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['price' => '25.00', 'stock_qty' => 10]);
        $this->actingAs($customer);
        foreach ([1, 5, 5, 2] as $quantity) {
            $this->putJson(route('cart.quantity', $item), ['quantity' => $quantity, 'context' => 'menu'])
                ->assertOk()->assertJsonPath('cart.items.0.quantity', $quantity)
                ->assertJsonPath('cart.items.0.unit_cents', 2500)->assertJsonPath('page', null)
                ->assertJsonStructure(['sidebar']);
        }
        $this->assertSame(2, $customer->cart()->sole()->items()->sole()->quantity);
        $this->assertSame(10, $item->fresh()->stock_qty);
        $this->putJson(route('cart.quantity', $item), ['quantity' => 0, 'context' => 'cart'])->assertOk()->assertJsonCount(0, 'cart.items');
        $this->putJson(route('cart.quantity', $item), ['quantity' => 1, 'context' => 'cart'])->assertOk();
        $this->assertSame(1, $customer->cart()->sole()->items()->sole()->quantity);
    }

    public function test_stock_stall_and_role_checks_still_apply_to_fast_cart_updates(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['stock_qty' => 2]);
        $this->actingAs($customer)->putJson(route('cart.quantity', $item), ['quantity' => 3, 'context' => 'menu'])->assertUnprocessable();
        $this->putJson(route('cart.quantity', $item), ['quantity' => 2, 'context' => 'menu'])->assertOk();
        $other = MenuItem::factory()->for(Stall::factory())->create();
        $this->putJson(route('cart.quantity', $other), ['quantity' => 1, 'context' => 'menu'])->assertUnprocessable();
        $item->update(['stock_qty' => 0, 'is_available' => false]);
        $this->putJson(route('cart.quantity', $item), ['quantity' => 1, 'context' => 'menu'])->assertOk();
        $item->delete();
        $this->putJson(route('cart.quantity', $item), ['quantity' => 0, 'context' => 'menu'])->assertOk();
        $this->putJson(route('cart.quantity', $item), ['quantity' => 1, 'context' => 'menu'])->assertUnprocessable();
        $this->actingAs(User::factory()->staff()->create())->putJson(route('cart.quantity', $other), ['quantity' => 1, 'context' => 'menu'])->assertForbidden();
        $this->actingAs(User::factory()->create())->putJson(route('cart.quantity', $other), ['quantity' => -1, 'context' => 'menu'])->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 0);
    }
}
