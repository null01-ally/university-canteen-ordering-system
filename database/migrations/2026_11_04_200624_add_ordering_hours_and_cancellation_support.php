<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stalls', function (Blueprint $table): void {
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->time('break_starts_at')->nullable();
            $table->time('break_ends_at')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'preparing', 'ready', 'completed', 'cancelled'])->default('pending')->change();
            $table->enum('payment_method', ['cash_at_pickup', 'cash_on_delivery'])->default('cash_at_pickup');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
        });
        DB::table('orders')->where('fulfillment_method', 'delivery')->update(['payment_method' => 'cash_on_delivery']);
    }

    public function down(): void
    {
        if (DB::table('orders')->where('status', 'cancelled')->exists()) {
            throw new RuntimeException('Preserve cancelled order history before removing cancellation support.');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'payment_method']);
            $table->enum('status', ['pending', 'preparing', 'ready', 'completed'])->default('pending')->change();
        });

        Schema::table('stalls', function (Blueprint $table): void {
            $table->dropColumn(['opens_at', 'closes_at', 'break_starts_at', 'break_ends_at']);
        });
    }
};
