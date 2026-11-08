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
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['customer_id', 'status', 'order_date'], 'orders_customer_status_date_index');
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['customer_id', 'created_at'], 'notifications_customer_created_index');
        });
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->index(['stall_id', 'deleted_at', 'category_id', 'name'], 'menu_stall_active_category_name_index');
        });
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->index(['admin_id', 'created_at'], 'reports_admin_created_index');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['user_type', 'is_active', 'name'], 'users_role_active_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_customer_status_date_index'));
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('notifications_customer_created_index'));
        Schema::table('menu_items', fn (Blueprint $table) => $table->dropIndex('menu_stall_active_category_name_index'));
        Schema::table('sales_reports', fn (Blueprint $table) => $table->dropIndex('reports_admin_created_index'));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_role_active_name_index'));
    }
};
