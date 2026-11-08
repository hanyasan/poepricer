<?php

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemBetaDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\DataTransferObjects\RepositoryObjects\ExchangeTypeDto;
use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemCurrentDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemInfoDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemSummaryDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemCategoriesDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueItemMoveDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueOverviewDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueUnusualMoveDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketBinDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketBreadthDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketCategoryDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketDistributionDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketItemDto;
use App\Enums\MetricPeriodEnum;
use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "storage", "octane",
    |                    "session", "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/locks'),
        ],

        'storage' => [
            'driver' => 'storage',
            'disk' => env('CACHE_STORAGE_DISK'),
            'path' => env('CACHE_STORAGE_PATH', 'framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    */

    'serializable_classes' => [
        ExchangeTypeDto::class,
        PoeVersionDto::class,
        MainPoeLeagueDto::class,
        ExchangeMarketMetricDto::class,
        PriceCheckDto::class,
        PoeLeagueDto::class,
        ExchangeItemDto::class,
        ExchangeMetricsItemDto::class,
        LeagueOverviewDto::class,
        LeagueItemMoveDto::class,
        LeagueUnusualMoveDto::class,
        LeagueItemCategoriesDto::class,
        MarketDistributionDto::class,
        MarketBreadthDto::class,
        MarketBinDto::class,
        MarketCategoryDto::class,
        MarketItemDto::class,
        ItemSummaryDto::class,
        ItemInfoDto::class,
        ItemCurrentDto::class,
        ExchangeItemBetaDto::class,
        MetricPeriodEnum::class,
    ],

];
