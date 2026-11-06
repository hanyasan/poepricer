<?php

namespace App\Jobs\Fetch;

use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Services\DataServices\Poe\PoeVersionServiceContract;
use App\Services\NinjaImport\NinjaLeaguePricerServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Tries(1)]
final class FetchPoeOnePricesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping)->releaseAfter(10)->expireAfter(60)];
    }

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('scheduled');
    }

    /**
     * Execute the job.
     */
    public function handle(
        NinjaLeaguePricerServiceContract $pricerService,
        PoeVersionServiceContract $versionService,
    ): void {
        /** @var PoeVersionDto $version */
        $version = collect($versionService->getAll())->firstWhere('version', 'poe1');

        $leagues = $pricerService->pickLeaguesForCheck($version);

        foreach ($leagues as $league) {
            FetchLeagueExchangePricesJob::dispatch($league);
        }
    }
}
