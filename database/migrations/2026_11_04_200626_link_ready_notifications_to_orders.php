<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['order_id', 'is_read']);
        });

        DB::table('notifications')->where('is_read', false)->orderBy('id')->chunkById(250, function ($notifications): void {
            $references = $notifications->mapWithKeys(function ($notification): array {
                return preg_match('/\AOrder ([0-9a-z]{26}) is ready\b/i', $notification->message, $matches)
                    ? [$notification->id => strtolower($matches[1])]
                    : [];
            });
            $orders = DB::table('orders')->whereIn('reference_number', $references->values())
                ->get()->keyBy(fn ($order): string => strtolower($order->reference_number));

            foreach ($notifications as $notification) {
                $order = $orders->get($references->get($notification->id));
                if ($order && $order->customer_id === $notification->customer_id) {
                    DB::table('notifications')->where('id', $notification->id)
                        ->update(['order_id' => $order->id, 'is_read' => $order->status !== 'ready']);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'is_read']);
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
