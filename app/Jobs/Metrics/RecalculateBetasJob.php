<?php

namespace App\Jobs\Metrics;

use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Tries(1)]
final class RecalculateBetasJob implements ShouldQueue
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
    public function handle(PoeLeagueRepositoryContract $leagueRepository): void
    {
        foreach ($leagueRepository->getActiveIdsWithMetricsItems() as $leagueId) {
            RecalculateBetasForLeagueJob::dispatch($leagueId);
        }
    }
}
