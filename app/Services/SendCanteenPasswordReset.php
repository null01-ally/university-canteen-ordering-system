<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

use function Illuminate\Support\defer;

class SendCanteenPasswordReset
{
    public function handle(string $email): void
    {
        $notification = DB::transaction(function () use ($email): ?array {
            User::where('email', $email)->where('is_active', true)->lockForUpdate()->first();
            $recipient = null;
            Password::sendResetLink(['email' => $email, 'is_active' => true], function (User $user, string $token) use (&$recipient): void {
                $recipient = [$user, $token];
            });

            return $recipient;
        }, 3);

        if ($notification !== null) {
            [$user, $token] = $notification;
            defer(function () use ($user, $token): void {
                try {
                    $user->sendPasswordResetNotification($token);
                } catch (TransportExceptionInterface $exception) {
                    report($exception);
                }
            });
        }

    }
}
