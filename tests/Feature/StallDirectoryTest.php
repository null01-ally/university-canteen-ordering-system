<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StallDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_filters_by_assigned_staff_and_stall_identifier(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['user_type' => 'admin']);
        $stall = Stall::factory()->create(['name' => 'Campus bites']);
        $staff = User::factory()->staff($stall)->create(['name' => 'Assigned Staff']);
        $this->actingAs($admin)->get(route('admin.stalls.index', ['search' => $staff->name]))->assertOk()->assertSee('Campus bites')->assertSee('Register new canteen stall');
        $this->get(route('admin.stalls.index', ['search' => 'ST-'.str_pad($stall->id, 3, '0', STR_PAD_LEFT)]))->assertOk()->assertSee('Campus bites');
        $this->actingAs($staff)->get(route('admin.stalls.index'))->assertForbidden();
    }

    public function test_registration_assigns_staff_and_starts_closed(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $staff = User::factory()->staff()->create();
        $this->actingAs($admin)->post(route('admin.stalls.store'), ['name' => 'Registered Stall', 'staff_id' => $staff->id, 'delivery_enabled' => 1])->assertSessionHasNoErrors();
        $stall = Stall::where('name', 'Registered Stall')->sole();
        $this->assertFalse($stall->is_active);
        $this->assertTrue($stall->delivery_enabled);
        $this->assertSame($stall->id, $staff->fresh()->stall_id);
        $this->post(route('admin.stalls.store'), ['name' => 'Invalid Stall', 'staff_id' => $admin->id, 'delivery_enabled' => 1])->assertSessionHasErrors('staff_id');
        $this->assertDatabaseMissing('stalls', ['name' => 'Invalid Stall']);
    }

    public function test_reassignment_and_delivery_controls_change_only_the_target_stall(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $target = Stall::factory()->create();
        $other = Stall::factory()->create(['delivery_enabled' => false]);
        $previous = User::factory()->staff($target)->create();
        $selected = User::factory()->staff($other)->create();
        $this->actingAs($admin)->patch(route('admin.stalls.reassign', $target), ['staff_id' => $selected->id])->assertSessionHasNoErrors();
        $this->assertSame($target->id, $selected->fresh()->stall_id);
        $this->assertNull($previous->fresh()->stall_id);
        $this->patch(route('admin.stalls.delivery', $target), ['delivery_enabled' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($target->fresh()->delivery_enabled);
        $this->assertFalse($other->fresh()->delivery_enabled);
        $this->patch(route('admin.stalls.update', $target), ['name' => 'Renamed Stall', 'description' => 'Campus favorites', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Stall', $target->fresh()->name);
    }

    public function test_deletion_preserves_linked_history_and_allows_an_empty_stall(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $used = Stall::factory()->create();
        Order::factory()->for($used)->create();
        $empty = Stall::factory()->create();
        $this->actingAs($admin)->delete(route('admin.stalls.destroy', $used))->assertSessionHasErrors('stall');
        $this->assertDatabaseHas('stalls', ['id' => $used->id]);
        $this->delete(route('admin.stalls.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('stalls', ['id' => $empty->id]);
    }
}
