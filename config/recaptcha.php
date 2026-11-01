<?php

$applicationHostname = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);
$trustedHostnames = env('RECAPTCHA_HOSTNAMES', $applicationHostname ?: 'localhost');

return [
    /*
    | Set RECAPTCHA_ENABLED=true and provide RECAPTCHA_SITE_KEY,
    | RECAPTCHA_SECRET_KEY, and RECAPTCHA_HOSTNAMES as a comma-separated
    | allow-list. Protection is disabled by default. The per-flow IP counter
    | admits three POSTs, then requires a challenge, and expires after 15
    | minutes. A successful required challenge is remembered for five fixed
    | minutes in the same browser session, IP, and form flow; reuse does not
    | reset or shorten the independent 15-minute IP counter. A protected request
    | while disabled clears that session's remembered verifications. The hostname
    | default is derived from APP_URL, never request Host.
    */
    'enabled' => (bool) env('RECAPTCHA_ENABLED', false),
    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    'trusted_hostnames' => array_values(array_filter(array_map(
        static fn (string $hostname): string => mb_strtolower(trim($hostname)),
        explode(',', (string) $trustedHostnames),
    ))),
];
