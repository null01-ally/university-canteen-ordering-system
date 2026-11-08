<?php

namespace Tests\Feature;

use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StallImageTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('stall.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD1sAAAAASUVORK5CYII='));
    }

    private function settings(Stall $stall): array
    {
        return ['name' => $stall->name, 'is_active' => 1, 'pickup_enabled' => 1, 'delivery_enabled' => 0, 'delivery_fee' => '0.00'];
    }

    public function test_admin_can_upload_a_stall_image_and_customers_can_see_it(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $stall = $staff->stall;
        $this->actingAs(User::factory()->create(['user_type' => 'admin']))->patch(route('admin.stalls.configuration.update', $stall), $this->settings($stall) + ['photo' => $this->photo()])->assertSessionHasNoErrors();
        $path = $stall->fresh()->image_path;
        $this->assertStringStartsWith('stalls/', $path);
        Storage::disk('public')->assertExists($path);
        $this->get(route('admin.stalls.configuration', $stall))->assertOk()->assertSee('Replace stall image');
        $this->actingAs(User::factory()->create())->get(route('stalls.index'))->assertOk()->assertSee(Storage::disk('public')->url($path));
    }

    public function test_replacing_and_removing_an_image_cleans_up_the_previous_file(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $stall = $staff->stall;
        Storage::disk('public')->put('stalls/old.png', 'old image');
        $stall->update(['image_path' => 'stalls/old.png']);
        $this->actingAs(User::factory()->create(['user_type' => 'admin']))->patch(route('admin.stalls.configuration.update', $stall), $this->settings($stall) + ['photo' => $this->photo()])->assertSessionHasNoErrors();
        $newPath = $stall->fresh()->image_path;
        Storage::disk('public')->assertMissing('stalls/old.png');
        Storage::disk('public')->assertExists($newPath);
        $this->patch(route('admin.stalls.configuration.update', $stall), $this->settings($stall))->assertSessionHasNoErrors();
        $this->assertSame($newPath, $stall->fresh()->image_path);
        $this->patch(route('admin.stalls.configuration.update', $stall), $this->settings($stall) + ['remove_photo' => 1])->assertSessionHasNoErrors();
        $this->assertNull($stall->fresh()->image_path);
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_invalid_or_oversized_uploads_do_not_replace_the_existing_image(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $stall = $staff->stall;
        $stall->update(['image_path' => 'stalls/old.png']);
        Storage::disk('public')->put('stalls/old.png', 'old image');
        $this->actingAs(User::factory()->create(['user_type' => 'admin']))->patch(route('admin.stalls.configuration.update', $stall), $this->settings($stall) + ['photo' => UploadedFile::fake()->create('unsafe.txt', 1, 'text/plain')])->assertSessionHasErrors('photo');
        $this->patch(route('admin.stalls.configuration.update', $stall), $this->settings($stall) + ['photo' => $this->photo()->size(2049)])->assertSessionHasErrors('photo');
        $this->assertSame('stalls/old.png', $stall->fresh()->image_path);
        Storage::disk('public')->assertExists('stalls/old.png');
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_staff_cannot_upload_for_another_stall_and_customers_cannot_upload(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();
        $other = Stall::factory()->create();
        $this->actingAs($staff)->patch(route('admin.stalls.configuration.update', $other), $this->settings($other) + ['photo' => $this->photo()])->assertForbidden();
        $this->actingAs(User::factory()->create())->patch(route('admin.stalls.configuration.update', $other), $this->settings($other) + ['photo' => $this->photo()])->assertForbidden();
        $this->assertNull($other->fresh()->image_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
