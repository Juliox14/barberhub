<?php

namespace App\Support;

use Illuminate\Http\Request;

final class RecaptchaVerification
{
    private const SESSION_KEY = 'adaptive_recaptcha_verifications';

    private const VALID_FLOWS = ['login', 'register', 'forgot-password'];

    private const PROOF_LIFETIME_SECONDS = 300;

    public function __construct(private readonly RecaptchaService $recaptcha) {}

    public function forgetAll(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function isRemembered(Request $request, string $flow, string $ip): bool
    {
        $fingerprint = $this->configurationFingerprint();
        $proofs = $request->session()->get(self::SESSION_KEY);
        $proof = is_array($proofs) ? ($proofs[$flow] ?? null) : null;

        if ($fingerprint === null || ! in_array($flow, self::VALID_FLOWS, true) || ! is_array($proof)) {
            $this->forget($request, $flow);

            return false;
        }

        $verifiedAt = $proof['verified_at'] ?? null;
        $storedSessionId = $proof['session_id'] ?? null;
        $storedIp = $proof['ip'] ?? null;
        $storedFlow = $proof['flow'] ?? null;
        $storedFingerprint = $proof['configuration'] ?? null;
        $now = now()->timestamp;

        $valid = is_int($verifiedAt)
            && $verifiedAt > 0
            && $verifiedAt <= $now
            && $now - $verifiedAt < self::PROOF_LIFETIME_SECONDS
            && is_string($storedSessionId)
            && hash_equals($request->session()->getId(), $storedSessionId)
            && is_string($storedIp)
            && hash_equals($ip, $storedIp)
            && is_string($storedFlow)
            && hash_equals($flow, $storedFlow)
            && is_string($storedFingerprint)
            && hash_equals($fingerprint, $storedFingerprint);

        if (! $valid) {
            $this->forget($request, $flow);
        }

        return $valid;
    }

    public function rememberSuccessfulChallenge(Request $request, string $flow, string $ip): void
    {
        $fingerprint = $this->configurationFingerprint();

        if ($fingerprint === null || ! in_array($flow, self::VALID_FLOWS, true)) {
            return;
        }

        $proofs = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($proofs)) {
            $proofs = [];
        }

        $proofs[$flow] = [
            'flow' => $flow,
            'ip' => $ip,
            'session_id' => $request->session()->getId(),
            'verified_at' => now()->timestamp,
            'configuration' => $fingerprint,
        ];

        $request->session()->put(self::SESSION_KEY, $proofs);
    }

    private function configurationFingerprint(): ?string
    {
        $hostnames = config('recaptcha.trusted_hostnames');
        $siteKey = config('recaptcha.site_key');
        $secretKey = config('recaptcha.secret_key');
        $applicationKey = config('app.key');

        if (! config('recaptcha.enabled')
            || ! $this->recaptcha->configured()
            || ! is_string($siteKey)
            || $siteKey === ''
            || ! is_string($secretKey)
            || $secretKey === ''
            || ! is_array($hostnames)
            || $hostnames === []
            || ! is_string($applicationKey)
            || $applicationKey === '') {
            return null;
        }

        foreach ($hostnames as $hostname) {
            if (! is_string($hostname) || trim($hostname) === '') {
                return null;
            }
        }

        return hash_hmac('sha256', serialize([$siteKey, $secretKey, $hostnames]), $applicationKey);
    }

    private function forget(Request $request, string $flow): void
    {
        if (! in_array($flow, self::VALID_FLOWS, true)) {
            return;
        }

        $proofs = $request->session()->get(self::SESSION_KEY);
        if (! is_array($proofs) || ! array_key_exists($flow, $proofs)) {
            return;
        }

        unset($proofs[$flow]);
        if ($proofs === []) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        $request->session()->put(self::SESSION_KEY, $proofs);
    }
}
