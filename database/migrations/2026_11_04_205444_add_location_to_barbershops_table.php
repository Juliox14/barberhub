<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbershops', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('street_address', 1000)->nullable();
            $table->decimal('latitude', 11, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->index(['country_code', 'region', 'city'], 'barbershops_location_filter_index');
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table): void {
            $table->dropIndex('barbershops_location_filter_index');
            $table->dropColumn(['country_code', 'region', 'city', 'street_address', 'latitude', 'longitude']);
        });
    }
};
