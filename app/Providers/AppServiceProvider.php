<?php

namespace App\Providers;

use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepository;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepository;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Repositories\Poe\PoeLeagueRepository;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Poe\ExchangeTypeService;
use App\Services\DataServices\Poe\ExchangeTypeServiceContract;
use App\Services\DataServices\Poe\PoeVersionService;
use App\Services\DataServices\Poe\PoeVersionServiceContract;
use App\Services\NinjaImport\NinjaImportService;
use App\Services\NinjaImport\NinjaImportServiceContract;
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
