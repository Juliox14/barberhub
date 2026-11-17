<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_google_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('barber_google_calendar_connections')->cascadeOnDelete();
            $table->string('google_calendar_id');
            $table->string('google_event_id', 64)->nullable();
            $table->string('sync_status')->default('pending');
            $table->unsignedBigInteger('desired_version')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['appointment_id', 'connection_id', 'google_calendar_id'], 'appointment_google_event_identity_unique');
            $table->index(['connection_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_google_calendar_events');
    }
};
