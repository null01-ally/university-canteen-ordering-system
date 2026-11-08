<?php

use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('canteen:role {email} {role} {--stall= : Stall ID for a staff account}', function (string $email, string $role): int {
    if (! in_array($role, ['customer', 'staff', 'admin'], true)) {
        $this->error('Role must be customer, staff, or admin.');

        return 1;
    }

    $user = User::where('email', strtolower(trim($email)))->first();
    if (! $user?->is_active) {
        $this->error('An active account is required.');

        return 1;
    }

    $stallId = $this->option('stall') ?? $user->stall_id ?? Stall::DEFAULT_ID;
    if ($role === 'staff' && (! ctype_digit((string) $stallId) || ! Stall::whereKey($stallId)->exists())) {
        $this->error('Choose an existing stall ID.');

        return 1;
    }

    DB::transaction(function () use ($user, $role, $stallId): void {
        $account = User::query()->lockForUpdate()->findOrFail($user->id);
        $account->user_type = $role;
        $account->stall_id = $role === 'staff' ? $stallId : null;
        $accessChanged = $account->isDirty(['user_type', 'stall_id']);
        $account->save();
        if ($accessChanged) {
            $account->revokeDatabaseSessions();
            $account->revokePasswordResetTokens();
        }
    }, 3);
    $this->info("Assigned the {$role} role to {$user->email}.");

    return 0;
})->purpose('Assign a role to an active canteen account');

Artisan::command('canteen:backup {--binary= : Full path to mariadb-dump or the XAMPP mysqldump executable}', function (): int {
    $connection = DB::connection();
    if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->error('The backup command requires a MySQL or MariaDB connection.');

        return 1;
    }

    $settings = $connection->getConfig();
    $database = $connection->getDatabaseName();
    $directory = storage_path('app/private/backups');
    File::ensureDirectoryExists($directory, 0700);
    $destination = $directory.'/canteen-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.sql';
    $temporary = $destination.'.partial';
    $dump = new Process([
        $this->option('binary') ?: 'mariadb-dump',
        '--host='.$settings['host'],
        '--port='.$settings['port'],
        '--user='.$settings['username'],
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--no-tablespaces',
        '--ignore-table-data='.$database.'.'.config('session.table'),
        '--ignore-table-data='.$database.'.'.config('auth.passwords.users.table'),
        '--result-file='.$temporary,
        '--',
        $database,
    ], env: ['MYSQL_PWD' => (string) ($settings['password'] ?? '')], timeout: 300);

    try {
        $dump->mustRun();
        if (! is_file($temporary) || filesize($temporary) === 0 || ! rename($temporary, $destination)) {
            $this->error('No usable backup file was saved.');

            return 1;
        }
    } catch (ExceptionInterface) {
        $this->error('Database backup failed. Check the dump executable and database backup permissions.');

        return 1;
    } finally {
        if (is_file($temporary)) {
            File::delete($temporary);
        }
    }

    $this->info('Backup saved to '.$destination);

    return 0;
})->purpose('Save a consistent database dump outside the public directory');
