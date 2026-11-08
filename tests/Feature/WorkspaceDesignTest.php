<?php

namespace Tests\Feature;

use App\Models\DigitalReceipt;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkspaceDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_staff_summary_is_stall_scoped_and_uses_manila_day_boundaries(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-06 12:00', 'Asia/Manila'));
        $staff = User::factory()->staff()->create();
        $start = now('Asia/Manila')->startOfDay()->utc();
        $first = Order::factory()->for($staff->stall)->create(['status' => 'completed', 'order_date' => $start, 'total_amount' => '100.25']);
        $second = Order::factory()->for($staff->stall)->create(['status' => 'completed', 'total_amount' => '50.25']);
        DigitalReceipt::factory()->for($first)->create(['total_paid' => '100.25']);
        DigitalReceipt::factory()->for($second)->create(['total_paid' => '50.25']);
        $item = MenuItem::factory()->for($staff->stall)->create();
        $first->lines()->create(['item_id' => $item->id, 'item_name' => 'Summary rice meal', 'quantity' => 2, 'unit_price' => 10]);
        $second->lines()->create(['item_id' => $item->id, 'item_name' => 'Summary rice meal', 'quantity' => 3, 'unit_price' => 10]);
        Order::factory()->for($staff->stall)->create(['status' => 'completed', 'order_date' => $start->copy()->subSecond(), 'total_amount' => 900]);
        Order::factory()->for(Stall::factory()->create())->create(['status' => 'completed', 'total_amount' => 900]);
        foreach (['cancelled', 'ready', 'pending', 'preparing'] as $status) {
            Order::factory()->for($staff->stall)->create(['status' => $status, 'total_amount' => 900]);
        }
        $response = $this->actingAs($staff)->get(route('staff.summary', ['stall_id' => 999]))->assertOk();
        $this->assertEquals('150.50', $response->viewData('revenue'));
        $this->assertEquals('150.50', $response->viewData('cashCollected'));
        $response->assertDontSee('GCash')->assertDontSee('Digital /');
        $this->assertSame(2, $response->viewData('completedCount'));
        $this->assertSame(1, $response->viewData('cancelledCount'));
        $this->assertSame(1, $response->viewData('unclaimedCount'));
        $this->assertEquals(5, $response->viewData('topItems')->sole()->portions);
        $this->get(route('staff.summary', ['print' => 1]))->assertOk()->assertSee('Print / Save PDF')->assertSee('Summary rice meal');
        $this->actingAs(User::factory()->create())->get(route('staff.summary'))->assertForbidden();
        $this->actingAs(User::factory()->create(['user_type' => 'admin']))->get(route('staff.summary'))->assertForbidden();
        $this->travelBack();
    }

    public function test_daily_summary_handles_no_sales(): void
    {
        $staff = User::factory()->staff()->create();
        $response = $this->actingAs($staff)->get(route('staff.summary'))->assertOk()->assertSee('No completed sales yet today.');
        $this->assertEquals(0, $response->viewData('revenue'));
        $this->assertSame(0, $response->viewData('completedCount'));
        $this->assertCount(0, $response->viewData('topItems'));
    }

    public function test_queue_tabs_include_all_stage_counts_and_default_to_the_requested_stage(): void
    {
        $this->withoutVite();
        $staff = User::factory()->staff()->create();
        Order::factory()->for($staff->stall)->create(['status' => 'ready']);
        Order::factory()->for(Stall::factory()->create())->create(['status' => 'pending']);
        $response = $this->actingAs($staff)->get(route('staff.orders.index', ['status' => 'ready']))->assertOk();
        $this->assertCount(3, $response->viewData('lanes'));
        $this->assertSame(0, $response->viewData('orders')['pending']->total());
        $this->assertSame(1, $response->viewData('orders')['ready']->total());
        $response->assertSee('role="tablist"', false)->assertSee('queue-card-grid')->assertSee('All caught up!');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame('true', $xpath->query('//*[@id="queue-tab-ready"]')->item(0)->getAttribute('aria-selected'));
        $this->assertFalse($xpath->query('//*[@id="queue-panel-ready"]')->item(0)->hasAttribute('hidden'));
        $this->assertTrue($xpath->query('//*[@id="queue-panel-pending"]')->item(0)->hasAttribute('hidden'));
    }

    public function test_kitchen_controls_save_json_and_staff_cannot_change_admin_configuration(): void
    {
        $this->withoutVite();
        $staff = User::factory()->staff()->create();
        $stall = $staff->stall;
        $item = MenuItem::factory()->for($stall)->create(['stock_qty' => 3]);
        $this->actingAs($staff)->patchJson(route('staff.stalls.update', $stall), ['is_active' => false, 'delivery_fee' => 999])
            ->assertOk()->assertJsonPath('state.is_active', false);
        $this->assertNotEquals(999, $stall->fresh()->delivery_fee);
        $this->patch(route('staff.stalls.update', $stall), [
            'name' => 'Updated kitchen stall', 'is_active' => 1, 'pickup_enabled' => 1,
            'delivery_enabled' => 1, 'delivery_fee' => '25.00', 'preparation_minutes' => 12,
            'phone' => '09171234567', 'opens_at' => '07:00', 'closes_at' => '18:00',
        ])->assertSessionHasNoErrors();
        $this->assertSame('25.00', $stall->fresh()->delivery_fee);
        $this->assertSame(12, $stall->fresh()->preparation_minutes);
        $this->patch(route('staff.stalls.update', Stall::factory()->create()), ['name' => 'Another stall'])->assertNotFound();
        $this->patchJson(route('staff.menu.availability', $item), ['is_available' => false])->assertOk()->assertJsonPath('state.is_available', false);
        $item->update(['stock_qty' => 0]);
        $this->patchJson(route('staff.menu.availability', $item), ['is_available' => true])->assertUnprocessable();
        $this->get(route('admin.stalls.configuration', $stall))->assertForbidden();
        $this->patch(route('admin.stalls.configuration.update', $stall), ['name' => 'Tampered'])->assertForbidden();
        $this->get('/staff/inventory')->assertOk()->assertSee('kds-inventory')->assertDontSee('SKU');
        $this->get('/staff/inventory?search=NoSuchMealHere')->assertOk()->assertDontSee($item->name)->assertSee('No items match.');
        $this->get('/staff/settings')->assertOk()->assertSee('Operating hours')->assertSee('Preparation time (minutes)');
        $this->get(route('staff.orders.index'))->assertOk()->assertSee('New Orders')->assertSee('Ready / Out for Delivery')
            ->assertDontSee('Orders today')->assertDontSee('workspace-queue-tabs');
    }

    public function test_workspace_pages_are_role_protected_and_render_the_new_navigation(): void
    {
        $this->withoutVite();
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $this->actingAs($staff)->get(route('staff.profile.edit'))->assertOk()->assertSee('Your staff assignment');
        $this->get(route('staff.stalls.index'))->assertOk()->assertSee('Service availability')->assertSee('Operating hours');
        $this->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk()->assertSee('System audit log');
        $this->get(route('admin.stalls.configuration', $staff->stall))->assertOk()->assertSee('Operating hours')->assertSee('Delivery fee (PHP)');
        $this->get(route('admin.reports.index'))->assertOk()->assertSee('Report preview');
        $this->get(route('staff.profile.edit'))->assertForbidden();
    }

    public function test_report_filters_exclude_other_stalls_and_unpaid_orders(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $stall = Stall::factory()->create();
        $order = Order::factory()->for($stall)->create(['status' => 'completed']);
        DigitalReceipt::factory()->create(['order_id' => $order->id, 'total_paid' => '85.00']);
        DigitalReceipt::factory()->create(['total_paid' => '500.00']);
        Order::factory()->for($stall)->create(['total_amount' => '200.00']);
        $query = ['stall' => $stall->id, 'from' => now(config('canteen.timezone'))->toDateString()];
        $response = $this->actingAs($admin)->get(route('admin.reports.index', $query))->assertOk();
        $this->assertEquals(85, $response->viewData('total'));
        $this->assertEquals(1, $response->viewData('count'));
        $csv = $this->get(route('admin.reports.index', $query + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('85', $csv);
        $this->assertStringNotContainsString('500', $csv);
    }

    public function test_staff_profile_changes_cannot_change_the_assignment_or_role(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->patch(route('staff.profile.update'), [
            'name' => 'Updated Staff', 'email' => $staff->email, 'contact_phone' => '09171234567',
            'current_password' => 'password', 'user_type' => 'admin', 'stall_id' => 999,
        ])->assertRedirect(route('staff.profile.edit'))->assertSessionHasNoErrors();
        $this->assertSame('staff', $staff->fresh()->user_type);
        $this->assertSame(1, $staff->fresh()->stall_id);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $staff->id, 'event' => 'staff.profile.update', 'result' => 'success']);
    }

    public function test_weekly_hours_close_unchecked_days_and_are_scoped_to_the_staff_stall(): void
    {
        $staff = User::factory()->staff()->create();
        $hours = [];
        foreach (range(1, 7) as $day) {
            $hours[$day] = ['enabled' => $day === 1 ? 1 : 0, 'opens' => '07:00', 'closes' => '17:00'];
        }
        $this->actingAs(User::factory()->create(['user_type' => 'admin']))->patch(route('admin.stalls.configuration.update', $staff->stall), [
            'name' => $staff->stall->name, 'is_active' => 1, 'delivery_enabled' => 0, 'pickup_enabled' => 1,
            'delivery_fee' => 20, 'weekly_hours' => $hours, 'preparation_minutes' => 8,
        ])->assertSessionHasNoErrors();
        $this->travelTo(now(config('canteen.timezone'))->startOfWeek()->setTime(9, 0));
        $this->assertTrue($staff->stall->fresh()->accepts('pickup'));
        $this->travel(1)->days();
        $this->assertFalse($staff->stall->fresh()->accepts('pickup'));
        $this->travelBack();
        $this->patch(route('admin.stalls.configuration.update', $staff->stall), [
            'name' => $staff->stall->name, 'is_active' => 1, 'delivery_enabled' => 0, 'pickup_enabled' => 1, 'delivery_fee' => 20,
            'weekly_groups' => [
                'weekdays' => ['enabled' => 1, 'opens' => '07:00', 'closes' => '17:00'],
                'saturday' => ['enabled' => 1, 'opens' => '08:00', 'closes' => '12:00'],
                'sunday' => ['enabled' => 0, 'opens' => '07:00', 'closes' => '17:00'],
            ],
        ])->assertSessionHasNoErrors();
        $saved = $staff->stall->fresh()->weekly_hours;
        $this->assertEquals($saved[1], $saved[5]);
        $this->assertSame('08:00', $saved[6]['opens']);
        $this->assertEquals(0, $saved[7]['enabled']);
    }
}
