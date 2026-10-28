<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('login_failed_attempts')->default(0);
            $table->timestamp('login_failed_window_started_at')->nullable();
            $table->timestamp('login_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'login_failed_attempts',
                'login_failed_window_started_at',
                'login_locked_until',
            ]);
        });
    }
};
