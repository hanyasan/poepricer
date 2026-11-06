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
    'metrics' => [
        'bucket_hours' => (int) env('METRICS_BUCKET_HOURS', 12),
        'window_buckets' => (int) env('METRICS_WINDOW_BUCKETS', 14),
        'min_residual_observations' => (int) env('METRICS_MIN_RESIDUAL_OBSERVATIONS', 4),
        'min_beta_observations' => (int) env('METRICS_MIN_BETA_OBSERVATIONS', 6),
        'beta_prior_weight' => (int) env('METRICS_BETA_PRIOR_WEIGHT', 14),
    ],
    'compact' => [
        'days_stay' => (int) env('COMPACT_DAYS_STAY', 9),
    ],
];
