<?php

return [
    'ninja' => [
        'base_url' => env('NINJA_BASE_URL'),
        'user_agent' => env('NINJA_USER_AGENT'),
        'timeout' => (int) env('NINJA_TIMEOUT', 3),
    ],
    'price_check' => [
        'default_hours_check_rule' => (int) env('PRICE_CHECK_DEFAULT_HOURS_CHECK', 5),
        'schedule_step_minutes' => [
            'poe1' => (int) env('PRICE_CHECK_SCHEDULE_STEP_ONE', 10),
            'poe2' => (int) env('PRICE_CHECK_SCHEDULE_STEP_TWO', 30),
        ],
    ],
    'metrics' => [
        'bucket_hours' => (int) env('METRICS_BUCKET_HOURS', 12),
        // Buckets collected on every run: the last closed one plus the missed runs before it.
        'buckets_to_collect' => max(1, (int) env('METRICS_BUCKETS_TO_COLLECT', 2)),
        'window_buckets' => (int) env('METRICS_WINDOW_BUCKETS', 14),
        'min_residual_observations' => (int) env('METRICS_MIN_RESIDUAL_OBSERVATIONS', 4),
        'min_beta_observations' => (int) env('METRICS_MIN_BETA_OBSERVATIONS', 6),
        'beta_prior_weight' => (int) env('METRICS_BETA_PRIOR_WEIGHT', 14),
        'min_hourly_units' => (float) env('METRICS_MIN_HOURLY_UNITS', 2.0),
        'full_weight_hourly_units' => (float) env('METRICS_FULL_WEIGHT_HOURLY_UNITS', 20.0),
        'items_table_min_observations' => (int) env('ITEMS_TABLE_MIN_OBSERVATIONS', 6),
        // Share of the cheapest items a metrics currency leaves out of the basket: min_price is
        // this percentile of the item prices, so only the items above it pass.
        'basket_min_price_percentile' => min(1.0, max(0.0, (float) env('METRICS_BASKET_MIN_PRICE_PERCENTILE', 0.6))),
    ],
    'overview' => [
        'top_limit' => (int) env('OVERVIEW_TOP_LIMIT', 10),
        'unusual_limit' => (int) env('OVERVIEW_UNUSUAL_LIMIT', 20),
        'unusual_residual_multiplier' => (float) env('OVERVIEW_UNUSUAL_RESIDUAL_MULTIPLIER', 2.0),
    ],
    'market' => [
        'buckets_limit' => (int) env('MARKET_BUCKETS_LIMIT', 28),
        'flat_threshold' => (float) env('MARKET_FLAT_THRESHOLD', 0.005),
        'bin_width' => (float) env('MARKET_BIN_WIDTH', 0.02),
        'bin_range' => (float) env('MARKET_BIN_RANGE', 0.30),
    ],
    'items' => [
        'per_page' => (int) env('ITEMS_PER_PAGE', 50),
        'max_per_page' => (int) env('ITEMS_MAX_PER_PAGE', 100),
        'buckets_per_page' => (int) env('ITEM_BUCKETS_PER_PAGE', 28),
        'buckets_max_per_page' => (int) env('ITEM_BUCKETS_MAX_PER_PAGE', 100),
    ],
    'queue' => [
        // Seconds between the league jobs of one fan-out, so a scheduled tick does not
        // start every league at once. Zero keeps them in a single burst.
        'fan_out_stagger_seconds' => max(0, (int) env('QUEUE_FAN_OUT_STAGGER_SECONDS', 20)),
    ],
    'compact' => [
        'days_stay' => (int) env('COMPACT_DAYS_STAY', 9),
    ],
    'base' => [
        'default_main_trade_id' => (string) env('BASE_MAIN_TRADE_ID', 'divine'),
        'sparkline_days' => (int) env('BASE_SPARKLINE_DAYS', 7),
    ],
];
