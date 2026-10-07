<?php

namespace App\Models;

use Database\Factories\BusinessHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['barbershop_id', 'weekday', 'opens_at', 'closes_at', 'closed'])]
class BusinessHour extends Model
{
    /** @use HasFactory<BusinessHourFactory> */
    use HasFactory;

    public const WEEKDAY_SUNDAY = 0;

    public const WEEKDAY_MONDAY = 1;

    public const WEEKDAY_TUESDAY = 2;

    public const WEEKDAY_WEDNESDAY = 3;

    public const WEEKDAY_THURSDAY = 4;

    public const WEEKDAY_FRIDAY = 5;

    public const WEEKDAY_SATURDAY = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'closed' => 'boolean',
        ];
    }

    public function barbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class);
    }

    /**
     * @return array<int, string>
     */
    public static function weekdayLabels(): array
    {
        return [
            self::WEEKDAY_SUNDAY => 'Domingo',
            self::WEEKDAY_MONDAY => 'Lunes',
            self::WEEKDAY_TUESDAY => 'Martes',
            self::WEEKDAY_WEDNESDAY => 'Miércoles',
            self::WEEKDAY_THURSDAY => 'Jueves',
            self::WEEKDAY_FRIDAY => 'Viernes',
            self::WEEKDAY_SATURDAY => 'Sábado',
        ];
    }
}
