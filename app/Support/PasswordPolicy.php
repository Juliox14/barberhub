<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Throwable;

final class PasswordPolicy
{
    private const PASSWORD_LIFETIME_DAYS = 90;

    public static function expired(User $user): bool
    {
        $value = $user->getRawOriginal('password_changed_at');

        if (! is_string($value) || trim($value) === '') {
            return true;
        }

        try {
            return CarbonImmutable::parse($value)->lessThanOrEqualTo(now()->subDays(self::PASSWORD_LIFETIME_DAYS));
        } catch (Throwable) {
            return true;
        }
    }
}
