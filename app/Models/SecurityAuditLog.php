<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'subject_id', 'barbershop_id', 'event', 'metadata', 'ip_address', 'user_agent'])]
class SecurityAuditLog extends Model
{
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    public function eventBarbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class, 'barbershop_id');
    }

    public function subjectBarbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class, 'subject_id');
    }

    public function hasShopSubject(): bool
    {
        return str_starts_with($this->event, 'platform.barbershop.');
    }

    public function hasUserSubject(): bool
    {
        return in_array($this->event, [
            'login.succeeded', 'logout', 'password.updated', 'password.reset',
            'security.account_locked', 'security.session_revoked',
            'session.transfer_confirmed', 'tenant.membership.created',
            'tenant.membership.updated', 'tenant.membership.deleted',
        ], true);
    }

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
