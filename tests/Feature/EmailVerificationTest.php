<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
    }

    public function test_registration_sends_verification_and_ordering_requires_it(): void
    {
        $this->withoutVite();
        Notification::fake();
        $this->post(route('register'), [
            'contact_phone' => '09171234567', 'name' => 'New Customer', 'email' => 'new@example.com', 'password' => 'secure-password', 'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('verification.notice'));
        $customer = User::where('email', 'new@example.com')->firstOrFail();
        Notification::assertSentTo($customer, VerifyEmail::class);
        $this->assertFalse($customer->hasVerifiedEmail());
        foreach (['menu.index', 'cart.index', 'checkout.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('verification.notice'));
        }
        $this->post(route('orders.store'), [])->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Resend verification email');
        $this->get(route('account.edit'))->assertOk();
    }

    public function test_signed_link_verifies_only_its_authenticated_owner_once(): void
    {
        Event::fake([Verified::class]);
        $customer = User::factory()->unverified()->create();
        $url = $this->verificationUrl($customer);
        $this->actingAs(User::factory()->unverified()->create())->get($url)->assertForbidden();
        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
        $this->actingAs($customer)->get($url)->assertRedirect(route('menu.index'));
        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect(route('menu.index'));
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_expired_and_tampered_links_are_rejected(): void
    {
        $customer = User::factory()->unverified()->create();
        $this->actingAs($customer)->get($this->verificationUrl($customer, -1))->assertForbidden();
        $this->get($this->verificationUrl($customer).'&tampered=1')->assertForbidden();
        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $customer->id, 'hash' => sha1('old@example.com')]);
        $this->get($wrongHash)->assertForbidden();
        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_resending_is_limited_to_once_per_sixty_seconds(): void
    {
        Notification::fake();
        $customer = User::factory()->unverified()->create();
        $this->actingAs($customer)->from(route('verification.notice'))->post(route('verification.send'))->assertRedirect(route('verification.notice'));
        $this->post(route('verification.send'))->assertTooManyRequests();
        Notification::assertSentToTimes($customer, VerifyEmail::class, 1);
        $this->travel(61)->seconds();
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertSentToTimes($customer, VerifyEmail::class, 2);
        $this->travelBack();
    }

    public function test_staff_and_admin_access_does_not_require_customer_verification(): void
    {
        $this->withoutVite();
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->staff()->unverified()->create())->get(route('staff.orders.index'))->assertOk();
        $this->get(route('verification.notice'))->assertForbidden();
        $this->actingAs(User::factory()->unverified()->create(['user_type' => 'admin']))->get(route('admin.users.index'))->assertOk();
    }

    public function test_verified_customers_do_not_receive_another_verification_email(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('verification.notice'))->assertRedirect(route('menu.index'));
        $this->post(route('verification.send'))->assertRedirect(route('menu.index'));
        Notification::assertNothingSent();
    }

    public function test_registration_survives_an_smtp_delivery_failure(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));
        $this->post(route('register'), [
            'contact_phone' => '09171234567', 'name' => 'New Customer', 'email' => 'offline@example.com', 'password' => 'secure-password', 'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('verification.notice'))->assertSessionHasErrors('verification');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'offline@example.com', 'email_verified_at' => null]);
    }

    public function test_email_changes_invalidate_verification_and_send_a_new_link(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $oldUrl = $this->verificationUrl($customer);
        $this->actingAs($customer)->patch(route('account.update'), [
            'name' => $customer->name, 'email' => 'changed@example.com', 'current_password' => 'password',
        ])->assertRedirect(route('verification.notice'));
        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($customer, VerifyEmail::class);
        $this->get($oldUrl)->assertForbidden();
        $this->get(route('menu.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_log_mailer_writes_the_branded_verification_link_without_smtp(): void
    {
        Storage::fake('verification_mail');
        $logPath = Storage::disk('verification_mail')->path('verification.log');
        config([
            'mail.default' => 'log', 'mail.mailers.log.channel' => 'verification_test',
            'logging.channels.verification_test' => ['driver' => 'single', 'path' => $logPath, 'level' => 'debug'],
        ]);
        app('mail.manager')->purge('log');
        $customer = User::factory()->unverified()->create();
        $customer->sendEmailVerificationNotification();
        $message = Storage::disk('verification_mail')->get('verification.log');
        $this->assertStringContainsString('/email/verify/'.$customer->id.'/', $message);
        $this->assertStringContainsString('signature=', $message);
        $this->assertStringContainsString('#6B1D2F', $message);
        $this->assertStringContainsString('Verify Email Address', $message);
        $this->assertStringContainsString('If you did not create a ZPPSU Canteen account, no further action is required.', $message);
        $this->assertFalse($customer->hasVerifiedEmail());
    }
}
