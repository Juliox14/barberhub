<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorAuthentication
{
    public function __construct(
        private readonly Google2FA $google2FA,
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    public function enabled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public function required(User $user): bool
    {
        return (bool) $user->is_platform_admin
            || in_array($user->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true)
            || $user->memberships()
                ->where('status', Membership::STATUS_ACTIVE)
                ->whereIn('role', [Membership::ROLE_OWNER, Membership::ROLE_ADMIN])
                ->exists();
    }

    public function begin(User $user): string
    {
        $secret = $this->google2FA->generateSecretKey();

        DB::transaction(function () use ($user, $secret): void {
            $lockedUser = $this->lockCurrentUser($user);
            if ($this->enabled($lockedUser)) {
                throw new TwoFactorOperationRejected;
            }

            $lockedUser->forceFill([
                'two_factor_pending_secret' => $secret,
                'two_factor_version' => $lockedUser->two_factor_version + 1,
            ])->save();
        });

        return $secret;
    }

    /** @return array<int, string>|null */
    public function confirm(User $user, string $code): ?array
    {
        $result = DB::transaction(function () use ($user, $code): ?array {
            $lockedUser = $this->lockCurrentUser($user);
            if ($this->enabled($lockedUser) || $lockedUser->two_factor_pending_secret === null) {
                return null;
            }

            $usedStep = $this->verifiedStep($lockedUser->two_factor_pending_secret, $code, null);
            if ($usedStep === null) {
                return null;
            }

            $recoveryCodes = $this->newRecoveryCodes();
            $lockedUser->forceFill([
                'two_factor_secret' => $lockedUser->two_factor_pending_secret,
                'two_factor_pending_secret' => null,
                'two_factor_recovery_codes' => array_map(static fn (string $code): string => Hash::make($code), $recoveryCodes),
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_step' => $usedStep,
                'two_factor_version' => $lockedUser->two_factor_version + 1,
            ])->save();

            return $recoveryCodes;
        });

        if ($result !== null) {
            $this->auditLogger->record('two_factor.enabled', $user, $user);
        }

        return $result;
    }

    public function verify(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if (($code === null) === ($recoveryCode === null)) {
            return false;
        }

        $result = DB::transaction(function () use ($user, $code, $recoveryCode): array {
            $lockedUser = $this->lockCurrentUser($user);
            if (! $this->enabled($lockedUser)) {
                return [false, false];
            }

            if ($code !== null) {
                $step = $this->verifiedStep(
                    $lockedUser->two_factor_secret,
                    $code,
                    $lockedUser->two_factor_last_used_step,
                );
                if ($step === null) {
                    return [false, false];
                }

                $lockedUser->forceFill([
                    'two_factor_last_used_step' => $step,
                    'two_factor_version' => $lockedUser->two_factor_version + 1,
                ])->save();

                return [true, false];
            }

            $storedCodes = $lockedUser->two_factor_recovery_codes ?? [];
            foreach ($storedCodes as $index => $hash) {
                if (is_string($hash) && Hash::check((string) $recoveryCode, $hash)) {
                    unset($storedCodes[$index]);
                    $lockedUser->forceFill([
                        'two_factor_recovery_codes' => array_values($storedCodes),
                        'two_factor_version' => $lockedUser->two_factor_version + 1,
                    ])->save();

                    return [true, true];
                }
            }

            return [false, false];
        });

        if ($result[1]) {
            $this->auditLogger->record('two_factor.recovery_used', $user, $user);
        }

        return $result[0];
    }

    /** @return array<int, string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $recoveryCodes = $this->newRecoveryCodes();
        DB::transaction(function () use ($user, $recoveryCodes): void {
            $lockedUser = $this->lockCurrentUser($user);
            if (! $this->enabled($lockedUser)) {
                throw new TwoFactorOperationRejected;
            }

            $lockedUser->forceFill([
                'two_factor_recovery_codes' => array_map(static fn (string $code): string => Hash::make($code), $recoveryCodes),
                'two_factor_version' => $lockedUser->two_factor_version + 1,
            ])->save();
        });

        $this->auditLogger->record('two_factor.recovery_regenerated', $user, $user);

        return $recoveryCodes;
    }

    public function disable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = $this->lockCurrentUser($user);
            if (! $this->enabled($lockedUser) || $this->required($lockedUser)) {
                throw new TwoFactorOperationRejected;
            }

            $lockedUser->forceFill([
                'two_factor_secret' => null,
                'two_factor_pending_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_step' => null,
                'two_factor_version' => $lockedUser->two_factor_version + 1,
            ])->save();
        });

        $this->auditLogger->record('two_factor.disabled', $user, $user);
    }

    /** @return array<int, string> */
    private function newRecoveryCodes(): array
    {
        return array_map(static fn (): string => strtoupper(bin2hex(random_bytes(8))), range(1, 10));
    }

    private function verifiedStep(string $secret, string $code, ?int $lastUsedStep): ?int
    {
        if (! preg_match('/\A[0-9]{6}\z/', $code)) {
            return null;
        }

        // A single adjacent 30-second timestep tolerates clock skew while rejecting old, expired codes.
        $step = $this->google2FA->verifyKeyNewer($secret, $code, $lastUsedStep ?? -1, 1);

        return is_int($step) ? $step : null;
    }

    private function lockCurrentUser(User $user): User
    {
        $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
        if ($lockedUser->two_factor_version !== $user->two_factor_version
            || ! hash_equals((string) $lockedUser->getRawOriginal('password'), (string) $user->getRawOriginal('password'))) {
            throw new TwoFactorOperationRejected;
        }

        return $lockedUser;
    }
}

final class TwoFactorOperationRejected extends \DomainException {}
