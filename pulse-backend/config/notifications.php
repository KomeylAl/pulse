<?php

return [
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'sender' => env('KAVENEGAR_SENDER'),
        ],
    ],

    'fcm' => [
        'web_icon' => env('FCM_WEB_ICON', '/icons/icon-192x192.png'),
        'default_link' => env('FCM_DEFAULT_LINK', env('FRONTEND_URL', 'http://localhost:3000')),
    ],
];
