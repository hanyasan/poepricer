<?php

namespace App\Providers;

use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepository;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\ExchangeItemPrice\ExchangeItemPriceRepository;
use App\Repositories\Poe\Exchange\ExchangeItemPrice\ExchangeItemPriceRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheckCore\PriceCheckCoreRepository;
use App\Repositories\Poe\Exchange\PriceCheckCore\PriceCheckCoreRepositoryContract;
use App\Repositories\Poe\PoeLeagueRepository;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Poe\ExchangeTypeService;
use App\Services\DataServices\Poe\ExchangeTypeServiceContract;
use App\Services\DataServices\Poe\PoeVersionService;
use App\Services\DataServices\Poe\PoeVersionServiceContract;
use App\Utility\Mapper\MapperService;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public array $singletons = [
        MapperServiceContract::class => MapperService::class,
        ExchangeItemRepositoryContract::class => ExchangeItemRepository::class,
        ExchangeItemPriceRepositoryContract::class => ExchangeItemPriceRepository::class,
        PriceCheckCoreRepositoryContract::class => PriceCheckCoreRepository::class,
        PoeLeagueRepositoryContract::class => PoeLeagueRepository::class,
        ExchangeTypeServiceContract::class => ExchangeTypeService::class,
        PoeVersionServiceContract::class => PoeVersionService::class,
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
