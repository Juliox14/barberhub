<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityAuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

final class RecordSecurityAuditEvent
{
    public function __construct(private SecurityAuditLogger $auditLogger) {}

    public function login(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->record('login.succeeded', $event->user, $event->user);
        }
    }

    public function logout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $this->auditLogger->record('logout', $user, $user);
    }

    public function passwordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->record('password.reset', null, $event->user);
        }
    }

    public function lockout(Lockout $event): void
    {
        $this->auditLogger->record('login.locked_out', null, null, request: $event->request);
    }
}
