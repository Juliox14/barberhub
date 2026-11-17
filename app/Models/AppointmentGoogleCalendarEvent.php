<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'connection_id', 'google_calendar_id', 'google_event_id', 'sync_status', 'desired_version', 'last_error', 'last_synced_at'])]
class AppointmentGoogleCalendarEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_ERROR = 'error';

    protected function casts(): array
    {
        return ['desired_version' => 'integer', 'last_synced_at' => 'datetime'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BarberGoogleCalendarConnection::class, 'connection_id');
    }
}
