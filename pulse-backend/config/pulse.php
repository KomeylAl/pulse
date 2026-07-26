<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default FCM presentation values for new projects
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'fcm_web_icon' => env('FCM_WEB_ICON', '/icons/icon-192x192.png'),
        'fcm_default_link' => env('FCM_DEFAULT_LINK', env('FRONTEND_URL', 'http://localhost:3000')),
    ],
];
