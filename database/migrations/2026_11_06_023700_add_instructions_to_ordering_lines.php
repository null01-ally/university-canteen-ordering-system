<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ordering_lines', function (Blueprint $table) {
            Schema::table('cart_items', fn (Blueprint $table) => $table->string('instructions', 500)->nullable());
            Schema::table('order_lines', fn (Blueprint $table) => $table->string('instructions', 500)->nullable());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordering_lines', function (Blueprint $table) {
            Schema::table('cart_items', fn (Blueprint $table) => $table->dropColumn('instructions'));
            Schema::table('order_lines', fn (Blueprint $table) => $table->dropColumn('instructions'));
        });
    }
};
