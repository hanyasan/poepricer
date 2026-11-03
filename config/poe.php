<?php

return [
    'ninja' => [
        'base_url' => env('NINJA_BASE_URL'),
        'user_agent' => env('NINJA_USER_AGENT'),
    ],
    'price_check' => [
        'default_hours_check_rule' => env('PRICE_CHECK_DEFAULT_HOURS_CHECK', 5),
    ],
];
