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
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->foreignId('stall_id')->default(1)->constrained()->restrictOnDelete();
            $table->string('image_path')->nullable();
            $table->index(['stall_id', 'category_id']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('stall_id')->default(1)->constrained()->restrictOnDelete();
            $table->enum('fulfillment_method', ['pickup', 'delivery'])->default('pickup');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('stall_id')->default(1)->constrained()->restrictOnDelete();
            $table->enum('fulfillment_method', ['pickup', 'delivery'])->default('pickup');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('delivery_location')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->index(['stall_id', 'status', 'order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['stall_id', 'status', 'order_date']);
            $table->dropConstrainedForeignId('stall_id');
            $table->dropColumn(['fulfillment_method', 'contact_name', 'contact_phone', 'delivery_location', 'notes', 'delivery_fee']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stall_id');
            $table->dropColumn('fulfillment_method');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex(['stall_id', 'category_id']);
            $table->dropConstrainedForeignId('stall_id');
            $table->dropColumn('image_path');
        });
    }
};
