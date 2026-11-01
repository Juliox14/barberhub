<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class RecaptchaService
{
    public function configured(): bool
    {
        return is_string(config('recaptcha.site_key'))
            && config('recaptcha.site_key') !== ''
            && is_string(config('recaptcha.secret_key'))
            && config('recaptcha.secret_key') !== ''
            && is_array(config('recaptcha.trusted_hostnames'))
            && config('recaptcha.trusted_hostnames') !== [];
    }

    public function siteKey(): ?string
    {
        return $this->configured() ? config('recaptcha.site_key') : null;
    }

    public function verify(string $token, string $ip): bool
    {
        if (! $this->configured() || $token === '' || mb_strlen($token) > 4096) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(5)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['success'] ?? null) !== true || ! is_string($payload['hostname'] ?? null)) {
            return false;
        }

        foreach (config('recaptcha.trusted_hostnames') as $hostname) {
            if (is_string($hostname) && hash_equals(mb_strtolower($hostname), mb_strtolower($payload['hostname']))) {
                return true;
            }
        }

        return false;
    }
}
