<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('stall_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('stalls', function (Blueprint $table): void {
            $table->boolean('pickup_enabled')->default(true);
            $table->boolean('delivery_enabled')->default(false);
        });

        DB::table('users')->where('user_type', 'staff')->update(['stall_id' => 1]);
        DB::table('stalls')->where('id', 1)->update([
            'delivery_enabled' => (bool) DB::table('canteen_settings')->where('id', 1)->value('delivery_enabled'),
        ]);
    }

    public function down(): void
    {
        DB::table('canteen_settings')->where('id', 1)->update([
            'delivery_enabled' => (bool) DB::table('stalls')->where('id', 1)->value('delivery_enabled'),
        ]);
        Schema::table('stalls', function (Blueprint $table): void {
            $table->dropColumn(['pickup_enabled', 'delivery_enabled']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stall_id');
        });
    }
};
