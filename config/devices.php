<?php

return [
    'activation_minutes' => (int) env('DEVICE_ACTIVATION_MINUTES', 30),
    'token_days' => (int) env('DEVICE_TOKEN_DAYS', 365),
    'offline_seconds' => (int) env('DEVICE_OFFLINE_SECONDS', 15),
];
