<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class CanteenAudit
{
    /** @param array<string, mixed> $entry */
    public function record(array $entry): void
    {
        try {
            DB::table('audit_events')->insert($entry + ['created_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
