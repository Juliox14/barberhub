<?php

return [
    'home' => 'Volver al inicio',
    '401' => [
        'title' => 'Inicia sesión para continuar',
        'message' => 'Necesitas iniciar sesión para acceder a esta página.',
        'guidance' => 'Vuelve al inicio y elige la opción de acceso para continuar.',
    ],
    '402' => [
        'title' => 'No se pudo completar la solicitud',
        'message' => 'Esta solicitud no está disponible en este momento.',
        'guidance' => 'Revisa la información e intenta nuevamente más tarde.',
    ],
    '403' => [
        'title' => 'Parece que no tienes acceso a esta página o se encuentra en mantenimiento',
        'message' => 'La página no está disponible con el acceso actual.',
        'guidance' => 'Vuelve al inicio o consulta con la persona responsable de tu cuenta si necesitás ayuda.',
    ],
    '404' => [
        'title' => 'No encontramos esta página',
        'message' => 'La página que buscás no existe o ya no está disponible.',
        'guidance' => 'Verifica la dirección o vuelve al inicio para seguir navegando.',
    ],
    '405' => [
        'title' => 'Esta acción no está disponible',
        'message' => 'No se puede realizar esta acción desde esta dirección.',
        'guidance' => 'Vuelve al inicio y buscá la opción adecuada para continuar.',
    ],
    '419' => [
        'title' => 'La página expiró',
        'message' => 'La sesión de esta página venció antes de completar la solicitud.',
        'guidance' => 'Vuelve al inicio y empieza de nuevo; no reenviamos la información automáticamente.',
    ],
    '429' => [
        'title' => 'Hay demasiadas solicitudes',
        'message' => 'Por un momento, recibimos más solicitudes de las que podemos atender.',
        'guidance' => 'Espera un poco antes de volver a intentarlo.',
    ],
    '500' => [
        'title' => 'Ocurrió un problema inesperado',
        'message' => 'No pudimos completar tu solicitud por un inconveniente del servicio.',
        'guidance' => 'Vuelve al inicio y prueba más tarde. Si el problema continúa, contacta al equipo de soporte.',
    ],
    '503' => [
        'title' => 'Servicio no disponible temporalmente',
        'message' => 'El servicio no está disponible temporalmente.',
        'guidance' => 'Espera un momento y vuelve a ingresar desde el inicio.',
    ],
    'generic' => [
        'client_code' => '4xx',
        'client_title' => 'No se pudo completar la solicitud',
        'client_message' => 'La solicitud no puede procesarse tal como fue enviada.',
        'client_guidance' => 'Revisa la información e intenta nuevamente o vuelve al inicio.',
        'server_code' => '5xx',
        'server_title' => 'Ocurrió un problema inesperado',
        'server_message' => 'No pudimos completar tu solicitud por un inconveniente del servicio.',
        'server_guidance' => 'Vuelve al inicio y prueba más tarde. Si el problema continúa, contacta al equipo de soporte.',
    ],
];
