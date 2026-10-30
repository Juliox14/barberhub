<?php

namespace App\Support;

use App\Events\AccountLoginLocked;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AccountLoginGuard
{
    private const MAX_FAILED_ATTEMPTS = 10;

    private const WINDOW_MINUTES = 15;

    public function lockedUser(string $email): ?User
    {
        $user = User::query()->where('email', $email)->first();

        return $user !== null && $this->isLocked($user) ? $user : null;
    }

    public function isLocked(User $user): bool
    {
        $lockedUntil = $user->getAttribute('login_locked_until');

        return $lockedUntil !== null && now()->lt(Carbon::parse($lockedUntil));
    }

    public function recordFailedAttempt(string $email): void
    {
        $lockEvent = DB::transaction(function () use ($email): ?array {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user === null) {
                return null;
            }

            $now = now();
            $lockedUntil = $user->getAttribute('login_locked_until');

            if ($lockedUntil !== null && $now->lt($lockedUntil)) {
                return null;
            }

            $windowStartedAt = $user->getAttribute('login_failed_window_started_at');
            $windowExpired = $windowStartedAt === null
                || $now->gte(Carbon::parse($windowStartedAt)->addMinutes(self::WINDOW_MINUTES));
            $failedAttempts = $windowExpired ? 0 : (int) $user->getAttribute('login_failed_attempts');
            $failedAttempts++;
            $shouldLock = $failedAttempts >= self::MAX_FAILED_ATTEMPTS;

            $user->forceFill([
                'login_failed_attempts' => $failedAttempts,
                'login_failed_window_started_at' => $windowExpired ? $now : $windowStartedAt,
                'login_locked_until' => $shouldLock ? $now->copy()->addMinutes(self::WINDOW_MINUTES) : null,
            ])->save();

            return $shouldLock
                ? [(int) $user->getKey(), Carbon::parse($user->getAttribute('login_locked_until'))->toISOString()]
                : null;
        });

        if ($lockEvent !== null) {
            event(new AccountLoginLocked($lockEvent[0], $lockEvent[1]));
        }
    }

    public function clearFailedWindow(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($this->isLocked($lockedUser)) {
                return;
            }

            $lockedUser->forceFill([
                'login_failed_attempts' => 0,
                'login_failed_window_started_at' => null,
                'login_locked_until' => null,
            ])->save();
        });
    }

    public function claimAfterCredentials(User $user, string $ownerToken, ?int $expectedVersion, SingleDeviceSession $sessions): bool
    {
        return DB::transaction(function () use ($user, $ownerToken, $expectedVersion, $sessions): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($this->isLocked($lockedUser)) {
                return false;
            }

            return $sessions->claimAfterCredentials($lockedUser, $ownerToken, $expectedVersion);
        });
    }

    public function confirmTransfer(User $user, string $ownerToken, ?int $expectedVersion, SingleDeviceSession $sessions): bool
    {
        return DB::transaction(function () use ($user, $ownerToken, $expectedVersion, $sessions): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($this->isLocked($lockedUser)) {
                return false;
            }

            return $sessions->confirmTransfer($lockedUser, $ownerToken, $expectedVersion);
        });
    }
}
