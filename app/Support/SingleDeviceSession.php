<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class SingleDeviceSession
{
    public const OWNER_TOKEN_KEY = 'single_device.owner_token';

    public const PENDING_KEY = 'single_device.pending';

    public function verifyCredentials(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();

        return $user !== null && Hash::check($password, $user->password) ? $user : null;
    }

    public function claimAfterCredentials(User $user, string $ownerToken): bool
    {
        return $this->claim($user, $ownerToken, false);
    }

    public function confirmTransfer(User $user, string $ownerToken): bool
    {
        return $this->claim($user, $ownerToken, true);
    }

    public function claimLegacySession(User $user, string $ownerToken): bool
    {
        return DB::transaction(function () use ($user, $ownerToken): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->active_session_id !== null) {
                return hash_equals($lockedUser->active_session_id, $ownerToken);
            }

            $lockedUser->forceFill([
                'active_session_id' => $ownerToken,
                'active_session_at' => now(),
            ])->save();

            return true;
        });
    }

    public function restoreRememberedSession(User $rememberedUser): ?string
    {
        return DB::transaction(function () use ($rememberedUser): ?string {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($rememberedUser->getKey());

            if (! hash_equals((string) $lockedUser->getRememberToken(), (string) $rememberedUser->getRememberToken())) {
                return null;
            }

            $ownerToken = $lockedUser->active_session_id;

            if ($ownerToken === null) {
                $ownerToken = Str::random(64);
                $lockedUser->active_session_id = $ownerToken;
            }

            $lockedUser->active_session_at = now();
            $lockedUser->save();

            return $ownerToken;
        });
    }

    public function touch(User $user, string $ownerToken): bool
    {
        return User::query()
            ->whereKey($user->getKey())
            ->where('active_session_id', $ownerToken)
            ->update(['active_session_at' => now()]) === 1;
    }

    public function release(User $user, string $ownerToken): void
    {
        DB::transaction(function () use ($user, $ownerToken): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->active_session_id === null || ! hash_equals($lockedUser->active_session_id, $ownerToken)) {
                return;
            }

            $lockedUser->forceFill([
                'active_session_id' => null,
                'active_session_at' => null,
                'remember_token' => Str::random(60),
            ])->save();
        });
    }

    private function claim(User $user, string $ownerToken, bool $confirmedTransfer): bool
    {
        return DB::transaction(function () use ($user, $ownerToken, $confirmedTransfer): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $currentOwner = $lockedUser->active_session_id;
            $lastActivity = $lockedUser->active_session_at;
            $isCurrentOwner = $currentOwner !== null && hash_equals($currentOwner, $ownerToken);
            $isExpired = $lastActivity === null
                || $lastActivity->lt(now()->subMinutes(config('session.lifetime')));

            if ($isCurrentOwner) {
                $lockedUser->forceFill(['active_session_at' => now()])->save();

                return true;
            }

            if ($currentOwner !== null && ! $confirmedTransfer && ! $isExpired) {
                return false;
            }

            if ($currentOwner !== null) {
                DB::table(config('session.table'))
                    ->where('user_id', $lockedUser->getKey())
                    ->delete();

                PushSubscription::query()->where('user_id', $lockedUser->getKey())->delete();
                $lockedUser->setRememberToken(Str::random(60));
            }

            $lockedUser->forceFill([
                'active_session_id' => $ownerToken,
                'active_session_at' => now(),
            ])->save();

            return true;
        });
    }
}
