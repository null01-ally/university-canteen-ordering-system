<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class CanteenHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_connection_errors_return_a_safe_retry_response(): void
    {
        config(['app.debug' => false]);
        Route::get('/database-outage-check', function (): never {
            $exception = new PDOException('Internal database host and credentials');
            $exception->errorInfo = ['HY000', 2002, 'Connection refused'];

            throw $exception;
        });

        $this->getJson('/database-outage-check')->assertStatus(503)->assertHeader('Retry-After', '30')
            ->assertExactJson(['message' => 'The canteen system cannot connect to its database. Please try again shortly.'])
            ->assertDontSee('Internal database host and credentials');
    }

    public function test_recovery_is_single_use_and_revokes_existing_database_sessions(): void
    {
        config(['session.driver' => 'database', 'app.url' => 'https://canteen.example.test']);
        Notification::fake();
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'another-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))->assertSessionHas('status');
        $notification = Notification::sent($user, ResetPassword::class)->sole();
        $this->assertStringStartsWith('https://canteen.example.test/reset-password/', $notification->toMail($user)->actionUrl);

        $data = [
            'email' => $user->email,
            'token' => $notification->token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
        $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
    }

    public function test_customer_password_change_revokes_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $customer = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'other-device',
            'user_id' => $customer->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($customer)->patch(route('account.update'), [
            'name' => $customer->name,
            'email' => $customer->email,
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('account.edit'));

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertTrue(Hash::check('new-secure-password', $customer->fresh()->password));
    }

    public function test_configured_registration_policy_restricts_domains_and_requires_approval(): void
    {
        config(['canteen.registration_domains' => ['zppsu.edu.ph'], 'canteen.require_account_approval' => true]);
        $registration = [
            'name' => 'New Customer',
            'contact_phone' => '09171234567',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];

        $this->post(route('register'), $registration + ['email' => 'student@example.com'])->assertSessionHasErrors('email');
        $this->post(route('register'), $registration + ['email' => 'student@zppsu.edu.ph'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['email' => 'student@zppsu.edu.ph', 'is_active' => false]);
    }

    public function test_operating_hours_and_breaks_are_enforced_at_cart_entry(): void
    {
        $stall = Stall::factory()->create(['opens_at' => '08:00', 'closes_at' => '17:00', 'break_starts_at' => '12:00', 'break_ends_at' => '13:00']);
        $item = MenuItem::factory()->for($stall)->create(['stock_qty' => 5]);
        $customer = User::factory()->create();

        $this->travelTo(Carbon::parse('2026-11-05 12:30', 'Asia/Manila'));
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasErrors('cart');
        $this->travelTo(Carbon::parse('2026-11-05 13:00', 'Asia/Manila'));
        $this->withSession(['canteen_last_interaction_at' => now()->timestamp]);
        $this->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-11-05 17:00', 'Asia/Manila'));
        $this->withSession(['canteen_last_interaction_at' => now()->timestamp]);
        $this->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasErrors('cart');
        $this->travelBack();
    }

    public function test_cancelling_a_pending_order_restores_stock_once_and_completed_orders_are_protected(): void
    {
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['stock_qty' => 0]);
        $pending = Order::factory()->for($customer, 'customer')->create();
        OrderLine::factory()->for($pending)->for($item, 'item')->create(['quantity' => 2]);
        $completed = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);

        $this->actingAs($customer)->patch(route('orders.cancel', $completed->reference_number))->assertSessionHasErrors('order');
        $this->patch(route('orders.cancel', $pending->reference_number))->assertRedirect(route('orders.show', $pending->reference_number));
        $this->patch(route('orders.cancel', $pending->reference_number))->assertSessionHasNoErrors();
        $this->assertSame(2, $item->fresh()->stock_qty);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame($customer->id, $pending->fresh()->cancelled_by);
    }
}
