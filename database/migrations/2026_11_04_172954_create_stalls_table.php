<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stalls', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('canteen_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('delivery_enabled')->default(false);
        });

        DB::table('canteen_settings')->insert(['id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canteen_settings');
        Schema::dropIfExists('stalls');
    }
};
