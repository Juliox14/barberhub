<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->string('role')->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['user_id', 'barbershop_id']);
            $table->index(['barbershop_id', 'role']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
