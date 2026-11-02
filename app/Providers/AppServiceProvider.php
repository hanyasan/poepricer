<?php

namespace App\Providers;

use App\Utility\Mapper\MapperService;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public array $singletons = [
        MapperServiceContract::class => MapperService::class,
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
