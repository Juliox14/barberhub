<?php

namespace App\Models;

use Database\Factories\CustomerPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'preferences'])]
class CustomerPreference extends Model
{
    /** @use HasFactory<CustomerPreferenceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
