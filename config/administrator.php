<?php

return [
    'name' => env('ADMIN_NAME', 'Warehouse Administrator'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
    'login_max_attempts' => 5,
    'login_decay_seconds' => 60,
];
