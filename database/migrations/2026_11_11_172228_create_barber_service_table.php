<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barber_service', function (Blueprint $table): void {
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['barber_id', 'service_id']);
            $table->index(['service_id', 'barber_id']);
        });

        DB::table('barber_service')->insertUsing(
            ['barber_id', 'service_id'],
            DB::table('barbers')
                ->join('services', 'services.barbershop_id', '=', 'barbers.barbershop_id')
                ->select('barbers.id', 'services.id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_service');
    }
};
