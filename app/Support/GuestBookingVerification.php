<?php

namespace App\Support;

use App\Models\Barbershop;
use App\Notifications\GuestBookingEmailCode as GuestBookingEmailCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class GuestBookingVerification
{
    private const CHALLENGE_TTL_MINUTES = 10;

    private const PROOF_TTL_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_WRONG_ATTEMPTS = 5;

    public function issue(Request $request, Barbershop $barbershop, string $email): void
    {
        $email = $this->normalizeEmail($email);
        $sessionId = $request->session()->getId();
        $challengeKey = $this->challengeKey($sessionId, $barbershop, $email);
        $proofKey = $this->proofKey($sessionId, $barbershop, $email);
        if (! $this->withinLimit('cooldown', $barbershop->getKey().'|'.$email, 1, self::RESEND_COOLDOWN_SECONDS)
            || ! $this->withinLimit('issue-email', $email, 10, 60)
            || ! $this->withinLimit('issue-ip', (string) $request->ip(), 20, 60)) {
            return;
        }

        Cache::forget($challengeKey);
        Cache::forget($proofKey);

        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(self::CHALLENGE_TTL_MINUTES)->getTimestamp();

        Cache::put($challengeKey, [
            'barbershop_id' => $barbershop->getKey(),
            'email' => $email,
            'session_id' => $sessionId,
            'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ], self::CHALLENGE_TTL_MINUTES * 60);

        try {
            Notification::route('mail', $email)->notify(new GuestBookingEmailCodeNotification($code));
        } catch (Throwable) {
            Cache::forget($challengeKey);
            Cache::forget($proofKey);
        }
    }

    public function verify(Request $request, Barbershop $barbershop, string $email, string $code): void
    {
        $email = $this->normalizeEmail($email);
        $sessionId = $request->session()->getId();
        $challengeKey = $this->challengeKey($sessionId, $barbershop, $email);
        $proofKey = $this->proofKey($sessionId, $barbershop, $email);
        Cache::forget($proofKey);

        $emailAllowed = $this->withinLimit('verify-email', $email, 30, 60);
        $ipAllowed = $this->withinLimit('verify-ip', (string) $request->ip(), 60, 60);
        $challenge = Cache::get($challengeKey);

        if (! $emailAllowed || ! $ipAllowed || ! is_array($challenge)) {
            return;
        }

        if (! isset($challenge['barbershop_id'], $challenge['email'], $challenge['session_id'], $challenge['code_hash'], $challenge['attempts'], $challenge['expires_at'])
            || ! is_string($challenge['email'])
            || ! is_string($challenge['session_id'])
            || ! is_string($challenge['code_hash'])
            || ! is_int($challenge['attempts'])
            || ! is_int($challenge['expires_at'])) {
            Cache::forget($challengeKey);

            return;
        }

        if ($challenge['expires_at'] <= now()->getTimestamp()
            || $challenge['barbershop_id'] !== $barbershop->getKey()
            || $challenge['email'] !== $email
            || $challenge['session_id'] !== $sessionId
            || $challenge['attempts'] >= self::MAX_WRONG_ATTEMPTS) {
            Cache::forget($challengeKey);

            return;
        }

        $providedHash = hash_hmac('sha256', $code, (string) config('app.key'));
        if (! hash_equals($challenge['code_hash'], $providedHash)) {
            $challenge['attempts']++;
            if ($challenge['attempts'] >= self::MAX_WRONG_ATTEMPTS) {
                Cache::forget($challengeKey);

                return;
            }

            Cache::put($challengeKey, $challenge, max(1, $challenge['expires_at'] - now()->getTimestamp()));

            return;
        }

        Cache::forget($challengeKey);
        Cache::put($proofKey, [
            'barbershop_id' => $barbershop->getKey(),
            'email' => $email,
            'session_id' => $sessionId,
        ], self::PROOF_TTL_MINUTES * 60);
    }

    public function verified(Request $request, Barbershop $barbershop, string $email): bool
    {
        $email = $this->normalizeEmail($email);
        $sessionId = $request->session()->getId();
        $proof = Cache::get($this->proofKey($sessionId, $barbershop, $email));

        return is_array($proof)
            && isset($proof['barbershop_id'], $proof['email'], $proof['session_id'])
            && is_string($proof['email'])
            && is_string($proof['session_id'])
            && $proof['barbershop_id'] === $barbershop->getKey()
            && $proof['email'] === $email
            && $proof['session_id'] === $sessionId;
    }

    public function consume(Request $request, Barbershop $barbershop, string $email): void
    {
        $email = $this->normalizeEmail($email);
        $sessionId = $request->session()->getId();
        Cache::forget($this->proofKey($sessionId, $barbershop, $email));
    }

    private function withinLimit(string $scope, string $identifier, int $maximum, int $decaySeconds): bool
    {
        $key = $this->key($scope, $identifier);
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            return false;
        }

        RateLimiter::hit($key, $decaySeconds);

        return true;
    }

    private function challengeKey(string $sessionId, Barbershop $barbershop, string $email): string
    {
        return $this->key('challenge', $barbershop->getKey().'|'.$sessionId.'|'.$email);
    }

    private function proofKey(string $sessionId, Barbershop $barbershop, string $email): string
    {
        return $this->key('proof', $barbershop->getKey().'|'.$sessionId.'|'.$email);
    }

    private function key(string $purpose, string $value): string
    {
        return 'guest-booking:'.$purpose.':'.hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
