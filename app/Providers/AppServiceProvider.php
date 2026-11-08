<?php

namespace App\Providers;

use App\Models\User;
use App\Services\CanteenAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(fn (User $user, string $url): MailMessage => (new MailMessage)
            ->subject('Verify your email · ZPPSU Canteen')
            ->view('emails.verify-email', ['name' => $user->name, 'verificationUrl' => $url]));
        View::composer('customer.desktop-header', function (\Illuminate\View\View $view): void {
            $customer = request()->user();
            $unreadCount = $view->getData()['unreadCount'] ?? $customer->pickupNotifications()->where('is_read', false)->count();
            $view->with('desktopUnreadCount', (int) $unreadCount);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            app(CanteenAudit::class)->record([
                'actor_id' => null,
                'actor' => 'Unauthenticated visitor',
                'event' => 'Failed sign-in',
                'target' => 'Campus account',
                'result' => 'failed',
                'details' => json_encode(['reason' => 'Credentials rejected']),
                'created_at' => now(),
            ]);
        });
        Event::listen(Login::class, function (Login $event): void {
            app(CanteenAudit::class)->record([
                'actor_id' => $event->user->id,
                'actor' => $event->user->name,
                'event' => 'Signed in',
                'target' => 'USR-'.$event->user->id,
                'result' => 'success',
                'details' => json_encode(['role' => $event->user->user_type]),
                'created_at' => now(),
            ]);
        });
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim(config('app.url'), '/')
            .route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], absolute: false));
    }
}
