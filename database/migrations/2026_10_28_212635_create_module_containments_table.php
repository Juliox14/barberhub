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
        Schema::create('module_containments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->string('module', 64);
            $table->string('reason', 1000);
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 1000)->nullable();
            $table->timestamps();

            $table->unique(['barbershop_id', 'module']);
            $table->index(['module', 'expires_at', 'released_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_containments');
    }
};
