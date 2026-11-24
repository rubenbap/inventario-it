<?php

return [
    'api_throttle_per_minute' => (int) env('API_THROTTLE_PER_MINUTE', 120),

    'api_max_limit' => 500,

    'asset_tag_digits' => 5,

    'enforce_same_company_checkout' => (bool) env('ENFORCE_SAME_COMPANY_CHECKOUT', true),

    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
        'first_name' => env('ADMIN_FIRST_NAME', 'Admin'),
    ],
];
