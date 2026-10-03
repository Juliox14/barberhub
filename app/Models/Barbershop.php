<?php

namespace App\Models;

use Database\Factories\BarbershopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status', 'timezone'])]
class Barbershop extends Model
{
    /** @use HasFactory<BarbershopFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
