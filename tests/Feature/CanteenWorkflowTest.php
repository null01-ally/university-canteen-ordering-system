<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DigitalReceipt;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CanteenWorkflowTest extends TestCase
{
    public function test_phone_number_is_required_and_saved_for_both_account_creation_flows(): void
    {
        Notification::fake();
        $data = ['name' => 'Phone Customer', 'email' => 'phone@example.com', 'password' => 'secure-password', 'password_confirmation' => 'secure-password'];
        $this->post(route('register'), $data)->assertSessionHasErrors('contact_phone');
        $this->post(route('register'), $data + ['contact_phone' => 'not-a-number'])->assertSessionHasErrors('contact_phone');
        $this->post(route('register'), $data + ['contact_phone' => '+63 917 123 4567'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'phone@example.com', 'contact_phone' => '+63 917 123 4567']);
        $this->actingAs(User::factory()->create(['user_type' => 'admin']));
        $data['email'] = 'admin-created@example.com';
        $data['user_type'] = 'customer';
        $this->post(route('admin.users.store'), $data)->assertSessionHasErrors('contact_phone');
        $this->post(route('admin.users.store'), $data + ['contact_phone' => 'invalid'])->assertSessionHasErrors('contact_phone');
        $this->post(route('admin.users.store'), $data + ['contact_phone' => '09171234567'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'admin-created@example.com', 'contact_phone' => '09171234567']);
    }

    use RefreshDatabase;

    public function test_order_history_hides_ready_alerts_for_completed_orders(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $ready = Order::factory()->for($customer, 'customer')->create(['status' => 'ready']);
        $completed = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);
        $current = "Order {$ready->reference_number} is ready for campus delivery.";
        $stale = "Order {$completed->reference_number} is ready. Pay in cash at the canteen when you pick it up.";
        $customer->pickupNotifications()->create(['message' => $current]);
        $customer->pickupNotifications()->create(['message' => $stale]);

        $this->actingAs($customer)->get(route('orders.index'))->assertOk()
            ->assertSee('Order history')->assertSee($completed->reference_number)
            ->assertSee($current)->assertDontSee($stale);
        $this->get(route('menu.index'))->assertOk()->assertSee($current)->assertDontSee($stale);
    }

    public function test_cart_steps_apply_to_the_current_quantity_even_when_stock_falls(): void
    {
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['stock_qty' => 5]);
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 2])->assertSessionHasNoErrors();
        $line = $customer->cart->items()->sole();
        $route = route('cart.update', $line);

        $this->patch($route, ['step' => 1])->assertSessionHasNoErrors();
        $this->patch($route, ['step' => 1])->assertSessionHasNoErrors();
        $this->assertSame(4, $line->fresh()->quantity);
        $item->update(['stock_qty' => 1]);
        $this->patch($route, ['step' => -1])->assertSessionHasNoErrors();
        $this->assertSame(3, $line->fresh()->quantity);
        $this->patch($route, ['step' => 1])->assertSessionHasErrors('quantity');
        $this->assertSame(3, $line->fresh()->quantity);
    }

    public function test_busy_pending_lane_does_not_hide_preparing_or_ready_orders(): void
    {
        $this->withoutVite();
        $staff = User::factory()->staff()->create();
        Order::factory()->count(16)->create(['order_date' => now()->subHour()]);
        $preparing = Order::factory()->create(['status' => 'preparing']);
        $ready = Order::factory()->create(['status' => 'ready']);

        $response = $this->actingAs($staff)->get(route('staff.orders.index', ['fragment' => 1]));
        $response->assertOk()->assertSee($preparing->reference_number)->assertSee($ready->reference_number);
        $this->assertSame(5, $response->viewData('orders')['pending']->count());
        $this->assertSame(16, $response->viewData('orders')['pending']->total());
    }

    public function test_staff_can_only_see_and_manage_their_assigned_stall(): void
    {
        $this->withoutVite();
        $stall = Stall::factory()->create(['name' => 'Assigned stall']);
        $other = Stall::factory()->create(['name' => 'Unassigned stall']);
        $staff = User::factory()->staff($stall)->create();
        $ownItem = MenuItem::factory()->for($stall)->create(['name' => 'Assigned item']);
        $otherItem = MenuItem::factory()->for($other)->create(['name' => 'Restricted item']);
        $ownOrder = Order::factory()->for($stall)->create();
        $otherOrder = Order::factory()->for($other)->create();

        $this->actingAs($staff)->get(route('staff.menu.index', ['stall' => $other->id]))
            ->assertOk()->assertSee($ownItem->name)->assertDontSee($otherItem->name);
        $this->get(route('staff.orders.index', ['stall' => $other->id, 'fragment' => 1]))
            ->assertOk()->assertSee($ownOrder->reference_number)->assertDontSee($otherOrder->reference_number);
        $this->get(route('staff.stalls.index'))->assertOk()->assertSee($stall->name)->assertDontSee($other->name);
        $this->get(route('staff.menu.edit', $otherItem))->assertNotFound();
        $this->patch(route('staff.menu.update', $otherItem))->assertNotFound();
        $this->patch(route('staff.menu.availability', $otherItem), ['is_available' => 0])->assertNotFound();
        $this->delete(route('staff.menu.destroy', $otherItem))->assertNotFound();
        $this->patch(route('staff.orders.update', $otherOrder->reference_number), ['status' => 'preparing'])->assertNotFound();
        $this->patch(route('staff.stalls.update', $other))->assertNotFound();

        $this->patch(route('staff.stalls.update', $stall), [
            'name' => 'Renamed stall',
            'is_active' => 1,
            'pickup_enabled' => 0,
            'delivery_enabled' => 1,
            'delivery_fee' => '15.00',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($stall->fresh()->accepts('delivery'));
        $this->assertFalse($stall->fresh()->accepts('pickup'));
        $this->assertSame('Renamed stall', $stall->fresh()->name);
        $this->assertFalse($other->fresh()->accepts('delivery'));
        $this->assertTrue($other->fresh()->accepts('pickup'));
    }

    public function test_customer_can_update_account_settings_and_sign_out(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $data = [
            'name' => 'Updated Customer',
            'email' => 'updated@example.com',
            'password' => 'new-account-password',
            'password_confirmation' => 'new-account-password',
        ];

        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('stalls.index'))->assertOk()->assertSee('Account settings');
        $this->get(route('menu.index'))->assertOk()->assertSee('Account settings');
        $this->get(route('account.edit'))->assertOk()->assertSee('Sign out');
        $this->patch(route('account.update'), $data + ['current_password' => 'incorrect'])->assertSessionHasErrors('current_password');
        $this->assertNotSame($data['email'], $customer->fresh()->email);
        $this->patch(route('account.update'), $data + ['current_password' => 'password'])->assertRedirect(route('verification.notice'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login'), ['email' => $data['email'], 'password' => $data['password']])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_campus_delivery_uses_the_selected_stall_fee_and_requires_contact_details(): void
    {
        $this->withoutVite();
        config(['canteen.campus_delivery_locations' => ['Library entrance']]);

        $customer = User::factory()->create();
        $stall = Stall::factory()->create(['delivery_fee' => '20.00']);
        $staff = User::factory()->staff($stall)->create();
        $item = MenuItem::factory()->for($stall)->create(['price' => '85.00', 'stock_qty' => 2]);

        $this->actingAs($customer)->get(route('stalls.index'))->assertOk()->assertSee($stall->name);
        $this->get(route('menu.index', ['stall' => $stall->id]))->assertOk()->assertSee($item->name);
        $this->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('cart.service'), ['method' => 'delivery'])->assertSessionHasErrors('method');

        $this->actingAs($staff)->patch(route('staff.delivery.update'), ['delivery_enabled' => 1])->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('cart.service'), ['method' => 'delivery'])->assertRedirect(route('cart.index'));
        $review = $this->get(route('checkout.index'))->assertOk()->assertSee('Cash on Delivery')->assertSee('105.00');
        $payload = ['snapshot' => $review->viewData('snapshot')];
        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('delivery_location');
        $this->post(route('orders.store'), $payload + [
            'contact_name' => $customer->name,
            'contact_phone' => '09171234567',
            'delivery_location' => 'Engineering Building, Room 204',
        ])->assertSessionHasNoErrors();

        $order = $customer->orders()->sole();
        $this->assertSame($stall->id, $order->stall_id);
        $this->assertSame('delivery', $order->fulfillment_method);
        $this->assertSame('cash_on_delivery', $order->payment_method);
        $this->assertSame('20.00', $order->delivery_fee);
        $this->assertSame('105.00', $order->total_amount);
        $this->get(route('orders.show', $order->reference_number))->assertOk()->assertSee('Track your order');
    }

    public function test_customers_can_register_and_sign_in_with_a_regular_email(): void
    {
        $this->withoutVite();

        $form = [
            'name' => 'Canteen Customer',
            'contact_phone' => '09171234567',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'user_type' => 'admin',
        ];

        $this->get(route('register'))->assertOk()->assertSee('Email address');
        $this->post(route('register'), $form + ['email' => 'invalid'])->assertSessionHasErrors('email');
        $email = 'customer@example.com';
        $this->post(route('register'), $form + ['email' => strtoupper($email)])->assertRedirect(route('verification.notice'));

        $customer = User::where('email', $email)->sole();
        $this->assertSame('customer', $customer->user_type);
        $this->assertNull($customer->email_verified_at);
        $this->assertAuthenticatedAs($customer);
        $this->get(route('menu.index'))->assertRedirect(route('verification.notice'));
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $customer->id, 'hash' => sha1($customer->email)]);
        $this->get($verificationUrl)->assertRedirect(route('menu.index'));
        $this->get(route('menu.index'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('register'), $form + ['email' => $email])->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => $email, 'password' => $form['password']])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_order_can_be_placed_prepared_collected_and_reported_once(): void
    {
        $this->withoutVite();

        $customer = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $item = MenuItem::factory()->create(['stock_qty' => 3]);

        $this->actingAs($customer)->get(route('menu.index'))->assertOk()->assertSee($item->name);
        $this->get(route('staff.orders.index'))->assertForbidden();
        $this->post(route('cart.store', $item), ['quantity' => 2])->assertSessionHasNoErrors();
        $cart = $this->get(route('cart.index'))->assertOk()->assertSee('130.00');
        $payload = ['snapshot' => $cart->viewData('snapshot')];

        $this->post(route('orders.store'), $payload)->assertSessionHasNoErrors();
        $order = $customer->orders()->sole();
        $this->assertSame('130.00', $order->total_amount);
        $this->assertSame(1, $item->fresh()->stock_qty);
        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('cart');
        $this->get(route('orders.show', $order->reference_number))->assertOk()->assertSee('Pending');

        $this->actingAs(User::factory()->create())->get(route('orders.show', $order->reference_number))->assertNotFound();
        $update = route('staff.orders.update', $order->reference_number);
        $this->actingAs($staff)->get(route('staff.orders.index', ['fragment' => '1']))->assertOk()->assertSee($order->reference_number);
        $this->patch($update, ['status' => 'ready'])->assertSessionHasErrors('status');
        $this->patch($update, ['status' => 'preparing'])->assertSessionHasNoErrors();
        $this->patch($update, ['status' => 'ready'])->assertSessionHasNoErrors();
        $this->patch($update, ['status' => 'ready'])->assertSessionHasNoErrors();
        $this->assertSame(3, $customer->pickupNotifications()->count());
        $this->actingAs($customer)->get(route('orders.index'))->assertOk()->assertSee('is ready');
        $notification = $customer->pickupNotifications()->whereLike('message', '%is ready%')->sole();
        $this->patch(route('notifications.read', $notification))->assertSessionHasNoErrors();
        $this->assertTrue($notification->fresh()->is_read);

        $this->actingAs($staff)->patch($update, ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->patch($update, ['status' => 'completed'])->assertSessionHasNoErrors();
        $receipt = DigitalReceipt::sole();
        $this->assertSame('130.00', $receipt->total_paid);
        $this->actingAs($customer)->get(route('orders.show', $order->reference_number))->assertOk()->assertSee($receipt->receipt_number);

        $day = $receipt->issue_date->timezone(config('canteen.timezone'))->toDateString();
        $this->actingAs($admin)->get(route('admin.sales.index', ['date' => $day]))->assertOk()->assertSee('130.00');
        $this->post(route('admin.sales.store'), ['date' => $day])->assertSessionHasNoErrors();
        $this->assertSame('130.00', $admin->salesReports()->sole()->total_sales);
    }

    public function test_checkout_rejects_a_stale_price_and_unavailable_stock(): void
    {
        $this->withoutVite();

        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['stock_qty' => 2]);
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 2])->assertSessionHasNoErrors();
        $snapshot = $this->get(route('cart.index'))->viewData('snapshot');

        $item->update(['price' => '80.00']);
        $this->post(route('orders.store'), ['snapshot' => $snapshot])->assertSessionHasErrors('cart');
        $item->update(['stock_qty' => 0]);
        $this->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasErrors('quantity');
        $this->post(route('orders.store'), ['snapshot' => $snapshot])->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::count());
        $this->assertSame(2, $customer->cart->items()->sole()->quantity);
    }

    public function test_staff_can_manage_menu_and_admin_can_deactivate_accounts(): void
    {
        $this->withoutVite();

        $staff = User::factory()->staff()->create();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $category = Category::factory()->create(['name' => 'Drinks']);
        $itemForm = [
            'name' => 'Calamansi Juice',
            'description' => 'Served chilled.',
            'price' => '25.00',
            'stock_qty' => 10,
            'is_available' => 1,
            'category_id' => $category->id,
        ];

        $this->actingAs($staff)->get(route('staff.menu.create'))->assertRedirect(route('staff.menu.index', ['create' => 1]));
        $this->get(route('staff.menu.index', ['create' => 1]))->assertOk()
            ->assertSee('name="category_id"', false)->assertSee('data-open-edit-dialog', false);
        $this->post(route('staff.menu.store'), $itemForm)->assertSessionHasNoErrors();
        $item = MenuItem::sole();
        $this->get(route('staff.menu.edit', $item))->assertRedirect(route('staff.menu.index', ['edit' => $item->id]));
        $this->get(route('staff.menu.index', ['edit' => $item->id]))->assertOk()->assertSee('data-open-edit-dialog', false);
        $this->patch(route('staff.menu.update', $item), array_replace($itemForm, [
            'original_stock_qty' => 10,
            'stock_qty' => 12,
        ]))->assertSessionHasNoErrors();
        $this->patch(route('staff.menu.availability', $item), ['is_available' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->canOrder());
        $this->delete(route('staff.menu.destroy', $item))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($item);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.users.create'))->assertRedirect(route('admin.users.index', ['create' => 1]));
        $this->get(route('admin.users.index', ['create' => 1]))->assertOk()->assertSee('data-open-edit-dialog', false);
        $this->from(route('admin.users.index'))->post(route('admin.users.store'), [
            'name' => 'New Staff',
            'contact_phone' => '09171234567',
            'email' => 'new-staff@example.com',
            'password' => 'new-staff-password',
            'user_type' => 'staff',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('stall_id');
        $this->get(route('admin.users.index'))->assertOk()->assertSee('data-open-edit-dialog', false)->assertSee('new-staff@example.com');
        $this->post(route('admin.users.store'), [
            'name' => 'New Staff',
            'contact_phone' => '09171234567',
            'email' => 'new-staff@example.com',
            'password' => 'new-staff-password',
            'user_type' => 'staff',
            'stall_id' => $staff->stall_id,
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new-staff@example.com', 'stall_id' => $staff->stall_id]);
        $this->get(route('admin.users.edit', $staff))->assertRedirect(route('admin.users.index', ['edit' => $staff->id]));
        $this->get(route('admin.users.index', ['edit' => $staff->id]))->assertOk()->assertSee('data-open-edit-dialog', false);
        $this->patch(route('admin.users.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff->fresh())->get(route('staff.orders.index'))->assertForbidden();
    }
}
