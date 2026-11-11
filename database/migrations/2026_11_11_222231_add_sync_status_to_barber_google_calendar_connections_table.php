<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barber_google_calendar_connections', function (Blueprint $table): void {
            $table->string('sync_status')->default('connected');
            $table->string('sync_error', 500)->nullable();
            $table->string('backfill_status')->default('pending');
        });
    }

    public function down(): void
    {
        Schema::table('barber_google_calendar_connections', function (Blueprint $table): void {
            $table->dropColumn(['sync_status', 'sync_error', 'backfill_status']);
        });
    }
};
