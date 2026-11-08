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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('contact_phone', 32)->nullable();
        });
        Schema::table('stalls', function (Blueprint $table): void {
            $table->string('location')->nullable();
            $table->string('description', 1000)->nullable();
            $table->unsignedSmallInteger('preparation_minutes')->default(8);
            $table->json('weekly_hours')->nullable();
        });
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor');
            $table->string('event');
            $table->string('target')->nullable();
            $table->string('result', 16);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['event', 'result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('contact_phone'));
        Schema::table('stalls', fn (Blueprint $table) => $table->dropColumn(['location', 'description', 'preparation_minutes', 'weekly_hours']));
    }
};
