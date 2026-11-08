<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SendCanteenPasswordReset;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function send(Request $request, SendCanteenPasswordReset $resetLinks): RedirectResponse
    {
        $email = $this->email($request);

        $resetLinks->handle($email);

        return back()->with('status', 'If an active account uses that email, a reset link will be sent.');
    }

    public function show(Request $request, string $token): View
    {
        $email = $request->query('email');

        return view('auth.reset-password', ['token' => $token, 'email' => is_string($email) ? $email : '']);
    }

    public function reset(Request $request): RedirectResponse
    {
        $email = $this->email($request);
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'confirmed', 'regex:/\A[^\x00]{1,72}\z/', PasswordRule::min(8)],
        ]);

        $result = DB::transaction(function () use ($email, $data): string {
            if (! User::where('email', $email)->where('is_active', true)->lockForUpdate()->first()) {
                return Password::INVALID_USER;
            }

            return Password::reset($data + ['email' => $email, 'is_active' => true], function (User $user, string $password): void {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->revokeDatabaseSessions();

                event(new PasswordReset($user));
            });
        }, 3);

        if ($result !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This password reset link is invalid or has expired. Request a new one.']);
        }

        $request->session()->regenerate(true);

        return to_route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }

    private function email(Request $request): string
    {
        return strtolower(trim($request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email']));
    }
}
