<?php

namespace App\Listeners;

use App\Events\AccountLoginLocked;
use App\Events\PlatformSecurityAction;
use App\Models\User;
use App\Support\SecurityAlertRecorder;
use App\Support\SecurityAuditLogger;

final class RecordContainmentSecurityEvent
{
    public function __construct(private SecurityAuditLogger $audit, private SecurityAlertRecorder $alerts) {}

    public function accountLocked(AccountLoginLocked $action): void
    {
        $event = 'security.account_locked';
        $reason = '10 intentos fallidos en 15 minutos; bloqueo de nuevos ingresos durante 15 minutos.';
        $this->audit->record($event, subject: $action->userId, metadata: ['expires_at' => $action->lockedUntil]);
        $this->alerts->record($event, $action->userId, null, null, $reason);
    }

    public function platformAction(PlatformSecurityAction $action): void
    {
        $event = match ($action->action) {
            'session_revoked' => 'security.session_revoked',
            'module_suspended' => 'platform.barbershop.module_suspended',
            'module_released' => 'platform.barbershop.module_released',
        };
        $metadata = ['reason' => $action->reason];
        if ($action->module !== null) {
            $metadata['module'] = $action->module;
        }
        if ($action->expiresAt !== null) {
            $metadata['expires_at'] = $action->expiresAt;
        }
        $this->audit->record(
            $event,
            actor: User::query()->find($action->userId),
            subject: $action->subjectUserId ?? $action->barbershopId,
            barbershopId: $action->barbershopId,
            metadata: $metadata,
        );
        $this->alerts->record($event, $action->subjectUserId, $action->barbershopId, $action->module, $action->reason);
    }
}
