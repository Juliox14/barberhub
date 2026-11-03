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
        Schema::create('appointment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_kind');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_kind');
            $table->dateTime('before_starts_at')->nullable();
            $table->dateTime('after_starts_at')->nullable();
            $table->string('before_status')->nullable();
            $table->string('after_status')->nullable();
            $table->timestamps();
            $table->index(['appointment_id', 'created_at']);
            $table->index(['barbershop_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_events');
    }
};
