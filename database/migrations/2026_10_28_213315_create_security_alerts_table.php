<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('event', 100);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('barbershop_id')->nullable();
            $table->string('module', 64)->nullable();
            $table->string('reason', 500);
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamp('next_email_at')->nullable();
            $table->timestamps();
            $table->index(['updated_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
