<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $credentials = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_phone' => ['required', 'string', 'max:32', 'regex:/\A\+?[0-9][0-9 ()-]{5,29}[0-9]\z/'],
            'password' => ['required', 'string', 'confirmed', 'regex:/\A[^\x00]{1,72}\z/', Password::min(8)],
        ]);
        $this->ensureApprovedDomain($credentials['email']);

        try {
            $user = DB::transaction(function () use ($credentials): User {
                $user = new User(['name' => $credentials['name'], 'email' => $credentials['email'], 'password' => $credentials['password']]);
                $user->contact_phone = $credentials['contact_phone'];
                $user->is_active = ! config('canteen.require_account_approval');
                $user->save();
                $user->revokePasswordResetTokens();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email address is already in use.']);
        }
        $verificationFailed = false;
        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $exception) {
            $verificationFailed = true;
            report($exception);
        }
        if (! $user->is_active) {
            return to_route('login')->with('status', 'Your account is awaiting administrator approval.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('canteen_last_interaction_at', now()->timestamp);

        return $verificationFailed
            ? to_route('verification.notice')->withErrors(['verification' => 'Your account was created, but the verification email could not be sent. Please try resending it later.'])
            : to_route('verification.notice');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'regex:/\A[^\x00]{1,72}\z/'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true])) {
            throw ValidationException::withMessages(['email' => 'These credentials could not be used to sign in.']);
        }

        $request->session()->regenerate();
        $request->session()->put('canteen_last_interaction_at', now()->timestamp);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'contact_phone' => ['nullable', 'string', 'regex:/^[0-9+() -]{7,32}$/'],
            'current_password' => ['required', 'string', 'regex:/\A[^\x00]{1,72}\z/', 'current_password'],
            'password' => ['nullable', 'string', 'confirmed', 'regex:/\A[^\x00]{1,72}\z/', Password::min(8)],
        ]);

        try {
            DB::transaction(function () use ($user, $data): void {
                $account = User::query()->lockForUpdate()->findOrFail($user->id);
                abort_unless($account->is_active && $account->user_type === $user->user_type && in_array($account->user_type, ['customer', 'staff'], true), 403);
                if (! Hash::check($data['current_password'], $account->password)) {
                    throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
                }

                $oldEmail = $account->email;
                if ($oldEmail !== $data['email']) {
                    $this->ensureApprovedDomain($data['email']);
                }
                $account->fill(['name' => $data['name'], 'email' => $data['email']]);
                if (array_key_exists('contact_phone', $data)) {
                    $account->contact_phone = $data['contact_phone'];
                }
                if ($account->isDirty('email')) {
                    $account->email_verified_at = null;
                }
                if (isset($data['password'])) {
                    $account->password = $data['password'];
                }
                $credentialsChanged = $account->isDirty(['email', 'password']);
                $account->save();
                if ($credentialsChanged) {
                    $account->revokePasswordResetTokens($oldEmail);
                    $account->revokeDatabaseSessions();
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email address is already in use.']);
        }
        $request->session()->regenerate(true);

        if ($user->user_type === 'customer' && $user->email !== $data['email']) {
            $user->refresh();
            try {
                $user->sendEmailVerificationNotification();
            } catch (TransportExceptionInterface $exception) {
                report($exception);
            }

            return to_route('verification.notice')->with('status', 'Your email address changed. Verify the new address to continue ordering.');
        }

        return to_route($user->user_type === 'staff' ? 'staff.profile.edit' : 'account.edit')->with('status', 'Account settings saved.');
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => strtolower(trim($email))]);
        }
    }

    private function ensureApprovedDomain(string $email): void
    {
        $domains = config('canteen.registration_domains');
        $domain = substr(strrchr($email, '@'), 1);
        if ($domains !== [] && ! in_array($domain, $domains, true)) {
            throw ValidationException::withMessages(['email' => 'Use an email address from an approved domain.']);
        }
    }
}
