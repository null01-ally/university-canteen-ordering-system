<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanteenAccess
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        abort_unless($user?->is_active && ($role === null || $user->user_type === $role), 403);
        abort_if($role === 'staff' && $user->stall === null, 403, 'Ask an administrator to assign your stall.');

        $now = now()->timestamp;
        $lastInteraction = (int) $request->session()->get('canteen_last_interaction_at', $now);
        if ($now - $lastInteraction >= max(1, (int) config('session.lifetime')) * 60) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your session expired due to inactivity. Please sign in again.');
        }
        if ($request->header('X-Canteen-Poll') !== '1' || ! $request->session()->has('canteen_last_interaction_at')) {
            $request->session()->put('canteen_last_interaction_at', $now);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
