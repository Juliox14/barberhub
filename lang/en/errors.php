<?php

return [
    'home' => 'Back to home',
    '401' => [
        'title' => 'Sign in to continue',
        'message' => 'You need to sign in to access this page.',
        'guidance' => 'Return home and choose the sign-in option to continue.',
    ],
    '402' => [
        'title' => 'We could not complete your request',
        'message' => 'This request is not available right now.',
        'guidance' => 'Check the information and try again later.',
    ],
    '403' => [
        'title' => 'You cannot access this page',
        'message' => 'This page is not available with your current access.',
        'guidance' => 'Return home or contact your account administrator if you need help.',
    ],
    '404' => [
        'title' => 'We could not find this page',
        'message' => 'The page you are looking for does not exist or is no longer available.',
        'guidance' => 'Check the address or return home to keep browsing.',
    ],
    '405' => [
        'title' => 'This action is not available',
        'message' => 'This action cannot be completed from this address.',
        'guidance' => 'Return home and find the right option to continue.',
    ],
    '419' => [
        'title' => 'This page has expired',
        'message' => 'Your session expired before the request could be completed.',
        'guidance' => 'Return home and start again; we will not resubmit your information automatically.',
    ],
    '429' => [
        'title' => 'Too many requests',
        'message' => 'We are receiving more requests than we can handle right now.',
        'guidance' => 'Wait a little while before trying again.',
    ],
    '500' => [
        'title' => 'Something unexpected happened',
        'message' => 'A service issue prevented us from completing your request.',
        'guidance' => 'Return home and try again later. If the problem continues, contact support.',
    ],
    '503' => [
        'title' => 'Service temporarily unavailable',
        'message' => 'The service is temporarily unavailable.',
        'guidance' => 'Wait a moment, then return from the home page.',
    ],
    'generic' => [
        'client_code' => '4xx',
        'client_title' => 'We could not complete your request',
        'client_message' => 'The request cannot be processed as sent.',
        'client_guidance' => 'Check the information and try again, or return home.',
        'server_code' => '5xx',
        'server_title' => 'Something unexpected happened',
        'server_message' => 'A service issue prevented us from completing your request.',
        'server_guidance' => 'Return home and try again later. If the problem continues, contact support.',
    ],
];
