<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCanteenAccess;
use App\Models\Category;
use App\Models\DigitalReceipt;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_volume_views_render_without_lazy_loading_and_queue_skips_retired_metrics(): void
    {
        $this->withoutVite();
        $staff = User::factory()->staff()->create();
        MenuItem::factory()->count(5)->for($staff->stall)->create();
        Order::factory()->count(5)->for($staff->stall)->create();
        Model::preventLazyLoading();
        try {
            $this->actingAs($staff);
            DB::enableQueryLog();
            $this->get(route('staff.orders.index'))->assertOk();
            $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
            $this->assertStringNotContainsString('digital_receipts', $queries);
            $this->assertStringNotContainsString('group by', strtolower($queries));
            DB::disableQueryLog();
            DB::flushQueryLog();
            $this->get(route('staff.inventory.index'))->assertOk();
            $customer = User::factory()->create();
            $this->actingAs($customer)->get(route('menu.index', ['stall' => $staff->stall_id]))->assertOk();
            $this->get(route('orders.index'))->assertOk();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
            Model::preventLazyLoading(false);
        }
    }

    public function test_reassigned_staff_cannot_finish_in_flight_writes_for_the_previous_stall(): void
    {
        $staff = User::factory()->staff()->create();
        $item = MenuItem::factory()->create();
        $order = Order::factory()->create();
        $other = Stall::factory()->create();
        $this->actingAs($staff)->withoutMiddleware(EnsureCanteenAccess::class);
        User::whereKey($staff->id)->update(['stall_id' => $other->id]);

        $this->patch(route('staff.menu.availability', $item), ['is_available' => false])->assertForbidden();
        $this->delete(route('staff.menu.destroy', $item))->assertForbidden();
        $this->patch(route('staff.orders.update', $order->reference_number), ['status' => 'preparing'])->assertForbidden();
        $this->patch(route('staff.delivery.update'), ['delivery_enabled' => true])->assertForbidden();
        $this->assertTrue($item->fresh()->is_available);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertFalse(Stall::findOrFail(Stall::DEFAULT_ID)->delivery_enabled);
    }

    public function test_demoted_administrator_cannot_finish_an_in_flight_account_or_stall_change(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($admin)->withoutMiddleware(EnsureCanteenAccess::class);
        User::whereKey($admin->id)->update(['user_type' => 'customer']);
        $this->post(route('admin.users.store'), [
            'name' => 'Unauthorized account', 'email' => 'unauthorized@example.com',
            'contact_phone' => '09171234567',
            'password' => 'strong-password', 'user_type' => 'admin',
        ])->assertForbidden();
        $this->patch(route('admin.stalls.delivery', Stall::DEFAULT_ID), ['delivery_enabled' => true])->assertForbidden();
        $this->post(route('admin.sales.store'))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.com']);
        $this->assertDatabaseCount('sales_reports', 0);
    }

    public function test_disabled_customer_cannot_cancel_and_restore_stock_from_an_in_flight_request(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer, 'customer')->create();
        $this->actingAs($customer)->withoutMiddleware(EnsureCanteenAccess::class);
        User::whereKey($customer->id)->update(['is_active' => false]);
        $this->patch(route('orders.cancel', $order->reference_number))->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_audit_storage_failure_does_not_break_a_successful_change_or_hide_validation(): void
    {
        $staff = User::factory()->staff()->create();
        $item = MenuItem::factory()->create();
        DB::connection()->beforeExecuting(function (string $query): void {
            if (str_starts_with(strtolower($query), 'insert') && str_contains($query, 'audit_events')) {
                throw new \RuntimeException('Simulated audit storage failure.');
            }
        });
        $this->actingAs($staff)->patch(route('staff.menu.availability', $item), ['is_available' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_available);
        $this->patch(route('staff.menu.availability', $item), ['is_available' => 'invalid'])
            ->assertSessionHasErrors('is_available');
    }

    public function test_polling_does_not_extend_an_idle_session_but_manual_navigation_does(): void
    {
        $this->freezeTime();
        $this->withoutVite();
        config(['session.lifetime' => 30]);
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('menu.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $started = now()->timestamp;
        $this->travel(20)->minutes();
        $this->get(route('menu.index'), ['X-Canteen-Poll' => '1'])->assertOk()
            ->assertSessionHas('canteen_last_interaction_at', $started);
        $this->get(route('menu.index'))->assertOk()
            ->assertSessionHas('canteen_last_interaction_at', now()->timestamp);
        $this->travel(31)->minutes();
        $this->get(route('menu.index'), ['X-Canteen-Poll' => '1'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_schedule_data_fails_closed_and_nonpositive_quantities_are_rejected(): void
    {
        $stall = new Stall(['weekly_hours' => [now(config('canteen.timezone'))->dayOfWeekIso => ['enabled' => true]]]);
        $this->assertFalse($stall->isTakingOrders());
        $this->assertFalse($stall->accepts('pickup'));
        $stall->weekly_hours = null;
        $stall->opens_at = '08:00';
        $this->assertFalse($stall->isTakingOrders());
        $item = MenuItem::factory()->make(['stock_qty' => 10]);
        $this->assertFalse($item->canOrder(0));
        $this->assertFalse($item->canOrder(-1));
    }

    public function test_replacing_menu_photo_removes_old_file_and_stale_edit_does_not_leak_upload(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $item = MenuItem::factory()->create(['image_path' => 'menu/old.png', 'stock_qty' => 5]);
        Storage::disk('public')->put('menu/old.png', 'old');
        $photo = fn () => UploadedFile::fake()->createWithContent('meal.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD1sAAAAASUVORK5CYII='));
        $data = [
            'name' => $item->name, 'description' => $item->description, 'price' => '65.00',
            'category_id' => $item->category_id, 'stock_qty' => 6,
            'original_stock_qty' => 5, 'is_available' => true,
        ];
        $this->actingAs($staff)->patch(route('staff.menu.update', $item), $data + ['photo' => $photo()])
            ->assertSessionHasNoErrors();
        $path = $item->fresh()->image_path;
        Storage::disk('public')->assertMissing('menu/old.png');
        Storage::disk('public')->assertExists($path);
        $this->patch(route('staff.menu.update', $item), $data + ['photo' => $photo()])->assertSessionHasErrors('stock_qty');
        $this->assertSame([$path], Storage::disk('public')->allFiles('menu'));
        $this->assertSame(6, $item->fresh()->stock_qty);
    }

    public function test_menu_query_count_is_bounded_as_inventory_grows_and_cart_count_stays_correct(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $category = Category::factory()->create();
        $items = MenuItem::factory()->count(3)->for($category)->create();
        $this->actingAs($customer)->post(route('cart.store', $items->first()), ['quantity' => 2])->assertSessionHasNoErrors();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('menu.index'))->assertOk()->assertSee('data-header-cart-count>2', false);
        $small = count(DB::getQueryLog());
        MenuItem::factory()->count(30)->for($category)->create();
        DB::flushQueryLog();
        $this->get(route('menu.index'))->assertOk()->assertSee('data-header-cart-count>2', false);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small + 1, $large);
    }

    public function test_sales_chart_groups_transactions_in_the_campus_timezone(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['user_type' => 'admin']);
        Order::factory()->count(3)->create(['order_date' => Carbon::parse('2026-11-05 00:05:00', 'UTC')]);
        Order::factory()->create(['order_date' => Carbon::parse('2026-11-04 16:05:00', 'UTC')]);
        Order::factory()->create(['order_date' => Carbon::parse('2026-11-05 16:05:00', 'UTC')]);
        $hours = $this->actingAs($admin)->get(route('admin.sales.index', ['date' => '2026-11-05']))->assertOk()->viewData('hours');
        $this->assertSame(3, $hours[8]);
        $this->assertSame(1, $hours[0]);
        $this->assertSame(4, array_sum($hours));
    }

    public function test_reports_accept_a_single_end_date_and_return_form_errors_for_reversed_dates(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.reports.index', ['to' => '2026-11-04']))->assertOk();
        $this->assertSame('2026-11-04', $response->viewData('from'));
        $this->get(route('admin.reports.index', ['from' => '2026-11-05', 'to' => '2026-11-04']))->assertSessionHasErrors('to');
    }

    public function test_workflow_indexes_are_present(): void
    {
        $expected = [
            'orders' => 'orders_customer_status_date_index',
            'notifications' => 'notifications_customer_created_index',
            'menu_items' => 'menu_stall_active_category_name_index',
            'sales_reports' => 'reports_admin_created_index',
            'users' => 'users_role_active_name_index',
        ];
        foreach ($expected as $table => $index) {
            $this->assertContains($index, array_column(Schema::getIndexes($table), 'name'));
        }
    }

    public function test_malformed_form_payloads_do_not_crash_the_next_page_render(): void
    {
        $this->withoutVite();
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->from(route('staff.menu.index'))->post(route('staff.menu.store'), ['name' => ['invalid']])
            ->assertSessionHasErrors('name');
        $this->get(route('staff.menu.index'))->assertOk();
        $this->actingAs(User::factory()->create(['user_type' => 'admin']))->patch(route('admin.stalls.configuration.update', Stall::DEFAULT_ID), [
            'weekly_groups' => ['weekdays' => ['enabled' => 1, 'opens' => ['invalid']], 'saturday' => [], 'sunday' => []],
        ])->assertSessionHasErrors();
        $this->actingAs($staff)->get(route('staff.stalls.index'))->assertOk();
    }

    public function test_long_stall_names_do_not_overflow_order_notification_storage(): void
    {
        $this->withoutVite();
        $stall = Stall::factory()->create(['name' => str_repeat('A', 255)]);
        $item = MenuItem::factory()->for($stall)->create();
        $this->actingAs(User::factory()->create())->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasNoErrors();
        $snapshot = $this->get(route('cart.index'))->viewData('snapshot');
        $this->post(route('orders.store'), ['snapshot' => $snapshot])->assertSessionHasNoErrors();
        $this->assertLessThanOrEqual(255, mb_strlen(DB::table('notifications')->sole()->message));
    }

    public function test_checkout_rolls_back_stock_order_and_cart_if_notification_storage_fails(): void
    {
        $this->withoutVite();
        $item = MenuItem::factory()->create(['stock_qty' => 5]);
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 2])->assertSessionHasNoErrors();
        $snapshot = $this->get(route('cart.index'))->viewData('snapshot');
        DB::connection()->beforeExecuting(function (string $query): void {
            if (str_starts_with(strtolower($query), 'insert') && str_contains($query, 'notifications')) {
                throw new \RuntimeException('Simulated notification storage failure.');
            }
        });
        $this->post(route('orders.store'), ['snapshot' => $snapshot])->assertServerError();
        $this->assertSame(5, $item->fresh()->stock_qty);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_lines', 0);
        $this->assertDatabaseHas('cart_items', ['item_id' => $item->id, 'quantity' => 2]);
    }

    public function test_csv_exports_neutralize_formulas_hidden_behind_whitespace(): void
    {
        $stall = Stall::factory()->create(['name' => '  =HYPERLINK("https://example.com")']);
        $order = Order::factory()->for($stall)->create(['status' => 'completed']);
        DigitalReceipt::factory()->for($order)->create();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $csv = $this->actingAs($admin)->get(route('admin.reports.index', ['format' => 'csv']))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertSame("'".$stall->name, $rows[1][2]);
    }
}
