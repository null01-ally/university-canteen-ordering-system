<?php

namespace Tests\Feature;

use App\Models\DigitalReceipt;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Database\Seeders\CanteenDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CanteenDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_data_supports_each_role_and_preserves_financial_and_inventory_integrity(): void
    {
        $this->withoutVite();
        $this->seed(CanteenDemoSeeder::class);
        $this->assertDatabaseCount('users', 9);
        $this->assertDatabaseCount('stalls', 4);
        $this->assertDatabaseCount('menu_items', 24);
        $this->assertDatabaseCount('orders', 64);
        $this->assertDatabaseCount('digital_receipts', 48);
        $this->assertDatabaseCount('cart_items', 6);
        $this->assertSame(3, Stall::all()->filter(fn (Stall $stall): bool => $stall->isTakingOrders())->count());
        $this->assertSame(4, MenuItem::where('stock_qty', 3)->count());
        $this->assertSame(4, MenuItem::where('stock_qty', 0)->where('is_available', false)->count());
        $this->assertSame(4, Order::where('status', 'pending')->count());
        $this->assertSame(4, Order::where('status', 'preparing')->count());
        $this->assertSame(4, Order::where('status', 'ready')->count());
        $this->assertSame(4, Order::where('status', 'cancelled')->count());
        foreach (Order::with(['lines', 'receipt'])->get() as $order) {
            $subtotal = $order->lines->sum(fn ($line): int => $line->quantity * (int) str_replace('.', '', $line->unit_price));
            $this->assertSame($subtotal + (int) str_replace('.', '', $order->delivery_fee), (int) str_replace('.', '', $order->total_amount));
            $this->assertSame($order->status === 'completed', $order->receipt !== null);
        }
        $this->assertEquals(DigitalReceipt::sum('total_paid'), User::where('user_type', 'admin')->sole()->salesReports()->sole()->total_sales);
        $customer = User::where('email', 'customer@example.com')->sole();
        $this->assertTrue(Hash::check(CanteenDemoSeeder::PASSWORD, $customer->password));
        $this->actingAs($customer)->get(route('menu.index'))->assertOk();
        $review = $this->get(route('checkout.index'))->assertOk();
        $this->post(route('orders.store'), ['snapshot' => $review->viewData('snapshot')])->assertSessionHasNoErrors();
        $staff = User::where('email', 'staff1@example.com')->sole();
        $pending = Order::where('stall_id', $staff->stall_id)->where('status', 'pending')->firstOrFail();
        $this->actingAs($staff)->patch(route('staff.orders.update', $pending->reference_number), ['status' => 'preparing'])->assertSessionHasNoErrors();
        $this->actingAs(User::where('email', 'admin@example.com')->sole())->get(route('admin.sales.index'))->assertOk();
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'disabled@example.com', 'password' => CanteenDemoSeeder::PASSWORD])->assertSessionHasErrors('email');
    }

    public function test_demo_seeder_refuses_to_mix_mock_records_with_existing_accounts(): void
    {
        User::factory()->create();
        $this->expectException(\RuntimeException::class);
        $this->seed(CanteenDemoSeeder::class);
    }
}
