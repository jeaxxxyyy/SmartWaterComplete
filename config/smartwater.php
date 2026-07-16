<?php

return [
    'demo' => [
        'user_email' => env('SMARTWATER_DEMO_USER', 'test@example.com'),
        'interval_seconds' => (int) env('SMARTWATER_DEMO_INTERVAL', 3),
        'device_name' => env('SMARTWATER_DEMO_DEVICE', 'ESP32-S3 Demo Panel'),
    ],
];
