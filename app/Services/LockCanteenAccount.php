<?php

namespace App\Services;

use App\Models\User;

class LockCanteenAccount
{
    public function handle(User $actor, string $role, ?int $stallId = null): User
    {
        $account = User::whereKey($actor->id)->lockForUpdate()->first();
        abort_unless($account?->is_active && $account->user_type === $role, 403);
        if ($role === 'staff') {
            abort_unless($stallId !== null && $account->stall_id === $stallId, 403, 'Your stall assignment changed. Reload your workspace.');
        }

        return $account;
    }
}
