<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['barbershop_id', 'module', 'reason', 'expires_at', 'released_at', 'release_reason'])]
class ModuleContainment extends Model
{
    public function barbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class);
    }

    /**
     * @return array<string, string>
     */
    public static function modules(): array
    {
        return [
            'appointments' => 'Citas e invitaciones de clientes',
            'members' => 'Miembros',
            'barbers' => 'Barberos e invitaciones',
            'services' => 'Servicios',
            'business-hours' => 'Horarios del negocio',
            'barber-availability' => 'Disponibilidad de barberos',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
