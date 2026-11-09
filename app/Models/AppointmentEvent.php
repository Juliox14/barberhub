<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'barbershop_id', 'appointment_id', 'customer_id', 'actor_kind', 'actor_user_id', 'event_kind',
    'before_starts_at', 'after_starts_at', 'before_status', 'after_status',
])]
class AppointmentEvent extends Model
{
    protected function casts(): array
    {
        return [
            'before_starts_at' => 'datetime',
            'after_starts_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function barbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function label(): string
    {
        return match ($this->event_kind) {
            'created' => 'Cita creada',
            'rescheduled' => 'Horario modificado',
            'cancelled' => 'Cita cancelada',
            'completed' => 'Cita completada',
            'no_show' => 'Cita marcada como no asistió',
            'restored' => 'Cita recuperada desde el historial',
            default => 'Cambio de cita',
        };
    }
}
