<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

final class TwoFactorLogin
{
    public const PENDING_KEY = 'two_factor.pending_login';

    public const VERIFIED_KEY = 'two_factor.verified';

    public function __construct(private readonly TwoFactorAuthentication $twoFactor) {}

    public function begin(Request $request, User $user, bool $authenticated = false): void
    {
        $request->session()->put(self::PENDING_KEY, [
            ...$this->fingerprint($user),
            'session_id' => $request->session()->getId(),
            'created_at' => now()->timestamp,
            'authenticated' => $authenticated,
            'remember' => $request->boolean('remember'),
        ]);
    }

    public function pendingUser(Request $request): User
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        abort_unless(is_array($pending)
            && ($pending['session_id'] ?? null) === $request->session()->getId()
            && is_int($pending['created_at'] ?? null)
            && $pending['created_at'] <= now()->timestamp
            && $pending['created_at'] > now()->subMinutes(10)->timestamp,
            419);
        $user = User::query()->find($pending['user_id'] ?? null);
        abort_unless($user !== null && $this->twoFactor->enabled($user)
            && $this->matches($pending, $user)
            && (! ($pending['authenticated'] ?? false) || $request->user()?->getAuthIdentifier() === $user->getKey()), 419);

        return $user;
    }

    public function verified(Request $request, User $user): bool
    {
        $proof = $request->session()->get(self::VERIFIED_KEY);

        return is_array($proof) && $this->matches($proof, $user);
    }

    public function markVerified(Request $request, User $user): void
    {
        $request->session()->put(self::VERIFIED_KEY, $this->fingerprint($user));
        $request->session()->forget(self::PENDING_KEY);
    }

    public function beginEnrollment(Request $request, User $user): void
    {
        $request->session()->put('two_factor.enrollment', [
            ...$this->fingerprint($user),
            'session_id' => $request->session()->getId(),
            'created_at' => now()->timestamp,
        ]);
    }

    public function requireEnrollmentConfirmation(Request $request, User $user): void
    {
        $proof = $request->session()->get('two_factor.enrollment');
        abort_unless(is_array($proof) && $this->matches($proof, $user)
            && ($proof['session_id'] ?? null) === $request->session()->getId()
            && is_int($proof['created_at'] ?? null)
            && $proof['created_at'] <= now()->timestamp
            && $proof['created_at'] > now()->subMinutes(10)->timestamp, 419);
    }

    public function enrollmentOverdue(User $user): bool
    {
        return $this->twoFactor->required($user) && ! $this->twoFactor->enabled($user)
            && ($user->two_factor_enrollment_started_at === null
                || now()->greaterThanOrEqualTo($user->two_factor_enrollment_started_at->copy()->addDays(14)));
    }

    /** @return array{user_id: int, password: string, security_version: int, factor_version: int} */
    private function fingerprint(User $user): array
    {
        return [
            'user_id' => (int) $user->getKey(),
            'password' => hash('sha256', $user->password),
            'security_version' => (int) $user->security_session_version,
            'factor_version' => (int) $user->two_factor_version,
        ];
    }

    /** @param array<string, mixed> $proof */
    private function matches(array $proof, User $user): bool
    {
        foreach ($this->fingerprint($user) as $key => $value) {
            if (($proof[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
