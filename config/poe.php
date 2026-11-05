<?php

return [
    'ninja' => [
        'base_url' => env('NINJA_BASE_URL'),
        'user_agent' => env('NINJA_USER_AGENT'),
        'timeout' => (int) env('NINJA_TIMEOUT', 3),
    ],
    'price_check' => [
        'default_hours_check_rule' => (int) env('PRICE_CHECK_DEFAULT_HOURS_CHECK', 5),
    ],
    'compact' => [
        'days_stay' => (int) env('COMPACT_DAYS_STAY', 7),
    ],
];
