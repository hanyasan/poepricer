<?php

namespace App\Providers;

use App\Repositories\Poe\Exchange\Compact\ExchangeCompactRepository;
use App\Repositories\Poe\Exchange\Compact\ExchangeCompactRepositoryContract;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepository;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeBucketPriceRepository;
use App\Repositories\Poe\Exchange\Metrics\ExchangeBucketPriceRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeItemTableRepository;
use App\Repositories\Poe\Exchange\Metrics\ExchangeItemTableRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepository;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepository;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepository;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Repositories\Poe\PoeLeagueRepository;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Compact\ExchangeCompactService;
use App\Services\DataServices\Compact\ExchangeCompactServiceContract;
use App\Services\DataServices\Metrics\ExchangeMetricsItemSeedService;
use App\Services\DataServices\Metrics\ExchangeMetricsItemSeedServiceContract;
use App\Services\DataServices\Metrics\ExchangeMetricsService;
use App\Services\DataServices\Metrics\ExchangeMetricsServiceContract;
use App\Services\DataServices\Metrics\LeagueItemsService;
use App\Services\DataServices\Metrics\LeagueItemsServiceContract;
use App\Services\DataServices\Metrics\LeagueMetricsLookupService;
use App\Services\DataServices\Metrics\LeagueMetricsLookupServiceContract;
use App\Services\DataServices\Metrics\LeagueOverviewService;
use App\Services\DataServices\Metrics\LeagueOverviewServiceContract;
use App\Services\DataServices\Metrics\PriceCheckCurrencyMixService;
use App\Services\DataServices\Metrics\PriceCheckCurrencyMixServiceContract;
use App\Services\DataServices\Poe\ExchangeTypeService;
use App\Services\DataServices\Poe\ExchangeTypeServiceContract;
use App\Services\DataServices\Poe\PoeLeagueService;
use App\Services\DataServices\Poe\PoeLeagueServiceContract;
use App\Services\DataServices\Poe\PoeVersionService;
use App\Services\DataServices\Poe\PoeVersionServiceContract;
use App\Services\NinjaImport\NinjaImportService;
use App\Services\NinjaImport\NinjaImportServiceContract;
use App\Services\NinjaImport\NinjaLeaguePricerService;
use App\Services\NinjaImport\NinjaLeaguePricerServiceContract;
use App\Utility\Mapper\MapperService;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public array $singletons = [
        MapperServiceContract::class => MapperService::class,
        ExchangeItemRepositoryContract::class => ExchangeItemRepository::class,
        PriceCheckRepositoryContract::class => PriceCheckRepository::class,
        PoeLeagueRepositoryContract::class => PoeLeagueRepository::class,
        ExchangeTypeServiceContract::class => ExchangeTypeService::class,
        PoeVersionServiceContract::class => PoeVersionService::class,
        NinjaImportServiceContract::class => NinjaImportService::class,
        NinjaLeaguePricerServiceContract::class => NinjaLeaguePricerService::class,
        ExchangeCompactRepositoryContract::class => ExchangeCompactRepository::class,
        ExchangeCompactServiceContract::class => ExchangeCompactService::class,
        ExchangeMetricsItemRepositoryContract::class => ExchangeMetricsItemRepository::class,
        ExchangeBucketPriceRepositoryContract::class => ExchangeBucketPriceRepository::class,
        ExchangeMetricsRepositoryContract::class => ExchangeMetricsRepository::class,
        ExchangeMetricsServiceContract::class => ExchangeMetricsService::class,
        PriceCheckCurrencyMixServiceContract::class => PriceCheckCurrencyMixService::class,
        ExchangeMetricsItemSeedServiceContract::class => ExchangeMetricsItemSeedService::class,
        PoeLeagueServiceContract::class => PoeLeagueService::class,
        LeagueOverviewServiceContract::class => LeagueOverviewService::class,
        ExchangeItemTableRepositoryContract::class => ExchangeItemTableRepository::class,
        LeagueItemsServiceContract::class => LeagueItemsService::class,
        LeagueMetricsLookupServiceContract::class => LeagueMetricsLookupService::class,
    ];

    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
