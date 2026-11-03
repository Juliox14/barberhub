<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbershops', function (Blueprint $table): void {
            $table->string('brand_primary_color', 7)->nullable();
            $table->string('brand_accent_color', 7)->nullable();
            $table->text('brand_description')->nullable();
            $table->string('brand_logo_path')->nullable();
            $table->string('brand_cover_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table): void {
            $table->dropColumn([
                'brand_primary_color',
                'brand_accent_color',
                'brand_description',
                'brand_logo_path',
                'brand_cover_path',
            ]);
        });
    }
};
