<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'subject_id', 'barbershop_id', 'event', 'metadata', 'ip_address', 'user_agent'])]
class SecurityAuditLog extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'subject_id' => 'integer',
            'barbershop_id' => 'integer',
            'metadata' => 'array',
        ];
    }
}
