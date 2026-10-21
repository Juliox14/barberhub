<?php

return [
    'public_key' => env('WEBPUSH_PUBLIC_KEY'),
    'private_key' => env('WEBPUSH_PRIVATE_KEY'),
    'subject' => env('WEBPUSH_SUBJECT'),
    'ttl' => 3600,
    'connect_timeout' => 3,
    'timeout' => 8,
];
