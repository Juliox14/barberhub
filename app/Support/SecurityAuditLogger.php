<?php

namespace App\Support;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class SecurityAuditLogger
{
    /** @var array<string, array<string, int>> */
    private const METADATA_LIMITS = [
        'login.succeeded' => [],
        'login.failed' => [],
        'login.locked_out' => [],
        'logout' => [],
        'password.updated' => [],
        'password.reset' => [],
        'session.transfer_confirmed' => [],
        'platform.barbershop.created' => ['name' => 255, 'slug' => 255, 'status' => 32, 'timezone' => 100],
        'platform.barbershop.updated' => ['name' => 255, 'slug' => 255, 'status' => 32, 'timezone' => 100],
        'platform.barbershop.deleted' => ['name' => 255, 'slug' => 255, 'status' => 32, 'timezone' => 100],
        'tenant.membership.created' => ['role' => 32, 'status' => 32],
        'tenant.membership.updated' => ['role' => 32, 'status' => 32],
        'tenant.membership.deleted' => ['role' => 32, 'status' => 32],
    ];

    /** @param array<string, string> $metadata */
    public function record(
        string $event,
        ?User $actor = null,
        User|int|null $subject = null,
        ?int $barbershopId = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        $allowedMetadata = self::METADATA_LIMITS[$event] ?? throw new InvalidArgumentException('Unsupported security audit event.');

        foreach ($metadata as $key => $value) {
            if (! isset($allowedMetadata[$key]) || ! is_string($value) || mb_strlen($value) > $allowedMetadata[$key]) {
                throw new InvalidArgumentException('Security audit metadata does not match the event allow-list.');
            }
        }

        $request ??= request();

        try {
            SecurityAuditLog::query()->create([
                'actor_id' => $actor?->getKey(),
                'subject_id' => $subject instanceof User ? $subject->getKey() : $subject,
                'barbershop_id' => $barbershopId,
                'event' => $event,
                'metadata' => $metadata,
                'ip_address' => $request->ip() === null ? null : mb_substr($request->ip(), 0, 45),
                'user_agent' => $request->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 500),
            ]);
        } catch (Throwable) {
            Log::error('Security audit log write failed.', ['event' => $event]);
        }
    }
}
