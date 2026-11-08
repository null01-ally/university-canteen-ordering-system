<?php

namespace Tests\Feature;

use App\Models\DigitalReceipt;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CanteenFeatureAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sales_csv_has_readable_utf8_transaction_rows(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $stall = Stall::factory()->create(['name' => '  =SUM(1,2)']);
        $customer = User::factory()->create(['name' => "Peña, Ana\nCampus staff"]);
        $order = Order::factory()->for($stall)->for($customer, 'customer')->create([
            'status' => 'completed', 'fulfillment_method' => 'pickup', 'payment_method' => 'cash_at_pickup',
            'contact_name' => null, 'total_amount' => '85.00',
        ]);
        DigitalReceipt::factory()->for($order)->create(['issue_date' => '2026-11-05 16:30:45', 'total_paid' => '85.00']);
        DigitalReceipt::factory()->create(['issue_date' => '2026-11-06 16:00:00', 'total_paid' => '999.00']);
        $response = $this->actingAs($admin)->get(route('admin.sales.export', ['date' => '2026-11-06']))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $headings = fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');
        $this->assertSame(['Order Reference', 'Stall Name', 'Customer Name', 'Fulfillment', 'Order Status', 'Payment Method', 'Payment Status', 'Total Amount (PHP)', 'Date & Time (Asia/Manila)'], $headings);
        $this->assertSame([$order->displayReference(), "'".$stall->name, $customer->name, 'Pickup', 'Completed', 'Cash at Pickup', 'Paid', '85.00', '2026-11-06 00:30:45'], $row);
        $this->assertFalse(fgetcsv($stream, escape: ''));
        fclose($stream);
        $this->actingAs($customer)->get(route('admin.sales.export'))->assertForbidden();
    }

    public function test_empty_admin_sales_csv_still_has_column_headers(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $csv = $this->actingAs($admin)->get(route('admin.sales.export'))->assertOk()->streamedContent();
        $this->assertCount(1, explode("\r\n", rtrim(substr($csv, 3), "\r\n")));
        $this->assertStringContainsString('Total Amount (PHP)', $csv);
    }

    public function test_admin_reset_links_require_admin_access_and_an_active_recipient(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $customer = User::factory()->create();
        $route = route('admin.users.password-reset', $customer);
        $this->actingAs($customer)->post($route)->assertForbidden();
        $this->actingAs($admin)->post($route)->assertSessionHasNoErrors();
        Notification::assertSentTo($customer, ResetPassword::class);
        $customer->is_active = false;
        $customer->save();
        $this->post($route)->assertSessionHasErrors('account');
    }

    public function test_service_can_be_changed_from_checkout_with_the_correct_fee(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $stall = Stall::factory()->create(['delivery_enabled' => true, 'delivery_fee' => '20.00']);
        $item = MenuItem::factory()->for($stall)->create(['price' => '85.00']);
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('cart.service'), ['method' => 'delivery', 'return_to' => 'checkout'])->assertRedirect(route('checkout.index'));
        $this->get(route('checkout.index'))->assertOk()->assertSee('105.00')->assertSee('Cash on Delivery');
        $this->post(route('cart.service'), ['method' => 'pickup', 'return_to' => 'checkout'])->assertRedirect(route('checkout.index'));
        $this->get(route('checkout.index'))->assertOk()->assertSee('85.00')->assertSee('Cash at Pickup');
    }

    public function test_role_changes_require_a_stall_and_preserve_self_admin_access(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $customer = User::factory()->create();
        $stall = Stall::factory()->create();
        $data = ['name' => $customer->name, 'email' => $customer->email, 'is_active' => 1, 'user_type' => 'staff'];
        $this->actingAs($admin)->patch(route('admin.users.update', $customer), $data)->assertSessionHasErrors('stall_id');
        $this->patch(route('admin.users.update', $customer), $data + ['stall_id' => $stall->id])->assertSessionHasNoErrors();
        $this->assertSame('staff', $customer->fresh()->user_type);
        $this->assertSame($stall->id, $customer->fresh()->stall_id);
        $this->patch(route('admin.users.update', $customer), array_replace($data, ['user_type' => 'customer']))->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->stall_id);
        $this->patch(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'is_active' => 1, 'user_type' => 'customer',
        ])->assertSessionHasErrors('user_type');
        $this->assertSame('admin', $admin->fresh()->user_type);
    }

    public function test_weekly_reports_separate_placed_orders_from_cash_collected_at_local_boundaries(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $paid = Order::factory()->create(['order_date' => '2026-11-01 16:00:00', 'status' => 'completed']);
        Order::factory()->create(['order_date' => '2026-11-08 15:59:59']);
        Order::factory()->create(['order_date' => '2026-11-08 16:00:00']);
        DigitalReceipt::factory()->create(['order_id' => $paid->id, 'issue_date' => '2026-11-01 16:00:00', 'total_paid' => '85.00']);
        $query = ['date' => '2026-11-05', 'period' => 'week'];
        $response = $this->actingAs($admin)->get(route('admin.sales.index', $query))->assertOk();
        $this->assertSame(2, $response->viewData('totals')->placed_orders);
        $this->assertEquals(1, $response->viewData('totals')->total_orders);
        $this->assertEquals(85, $response->viewData('totals')->total_sales);
        $this->assertSame(2, array_sum($response->viewData('hours')));
        $csv = $this->get(route('admin.sales.export', $query))->assertOk()->streamedContent();
        $this->assertStringContainsString('Order Reference', $csv);
        $this->assertStringContainsString($paid->displayReference(), $csv);
        $this->assertStringContainsString('85.00', $csv);
        $this->assertStringContainsString('2026-11-02 00:00:00', $csv);
    }

    public function test_monthly_report_persists_the_inclusive_calendar_range(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        Order::factory()->create(['order_date' => '2026-10-31 16:00:00']);
        Order::factory()->create(['order_date' => '2026-11-30 16:00:00']);
        $this->actingAs($admin)->post(route('admin.sales.store'), ['date' => '2026-11-05', 'period' => 'month'])->assertSessionHasNoErrors();
        $report = $admin->salesReports()->sole();
        $this->assertSame('2026-11-01', $report->period_start->toDateString());
        $this->assertSame('2026-11-30', $report->period_end->toDateString());
        $this->assertEquals(1, $report->total_orders);
        $this->get(route('admin.sales.index', ['period' => 'invalid']))->assertSessionHasErrors('period');
    }
}
