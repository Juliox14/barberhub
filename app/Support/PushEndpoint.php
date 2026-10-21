<?php

namespace App\Support;

final class PushEndpoint
{
    private const ALLOWED_HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'push.services.mozilla.com',
        'web.push.apple.com',
        'api.push.apple.com',
    ];

    public static function isValid(string $endpoint): bool
    {
        try {
            $parts = parse_url($endpoint);
        } catch (\ValueError) {
            return false;
        }

        if (! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || isset($parts['port']) && $parts['port'] !== 443
            || ! in_array(strtolower($parts['host']), self::ALLOWED_HOSTS, true)
            || empty($parts['path'])
        ) {
            return false;
        }

        return filter_var($endpoint, FILTER_VALIDATE_URL) !== false;
    }

    public static function decodeKey(string $key): ?string
    {
        if ($key === '' || preg_match('/\A[A-Za-z0-9_-]+={0,2}\z/', $key) !== 1) {
            return null;
        }

        $unpadded = rtrim($key, '=');
        $decoded = base64_decode(strtr($unpadded, '-_', '+/').str_repeat('=', (4 - strlen($unpadded) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }

    public static function isP256PublicKey(string $key): bool
    {
        $decoded = self::decodeKey($key);

        if ($decoded === null || strlen($decoded) !== 65 || $decoded[0] !== "\x04") {
            return false;
        }

        $derPrefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($derPrefix.$decoded), 64, "\n")."-----END PUBLIC KEY-----\n";

        return openssl_pkey_get_public($pem) !== false;
    }
}
