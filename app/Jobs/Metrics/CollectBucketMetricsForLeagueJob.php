<?php

namespace App\Jobs\Metrics;

use App\Services\DataServices\Metrics\ExchangeMetricsServiceContract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Tries(5)]
final class CollectBucketMetricsForLeagueJob implements ShouldQueue
{
    use Queueable;

    private int $leagueId;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('proceed_check_' . $this->leagueId))
                ->releaseAfter(10)
                ->expireAfter(60)
                ->shared()
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $leagueId,
    )
    {
        $this->leagueId = $leagueId;

        $this->onQueue('scheduled');
    }

    /**
     * Execute the job.
     */
    public function handle(
        ExchangeMetricsServiceContract $metricsService,
    ): void {
        $metricsService->collectBucketMetrics($this->leagueId, CarbonImmutable::now());
    }
}
