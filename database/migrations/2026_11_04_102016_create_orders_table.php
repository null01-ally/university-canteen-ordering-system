<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference_number')->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('order_date')->useCurrent();
            $table->enum('status', ['pending', 'preparing', 'ready', 'completed'])->default('pending');
            $table->decimal('total_amount', 12, 2);
            $table->index(['status', 'order_date']);
            $table->index(['customer_id', 'order_date']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
