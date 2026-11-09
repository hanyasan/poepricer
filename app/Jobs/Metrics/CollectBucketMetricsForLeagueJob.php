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
            (new WithoutOverlapping('proceed_check_'.$this->leagueId))
                ->releaseAfter(10)
                ->expireAfter(60)
                ->shared(),
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $leagueId,
    ) {
        $this->leagueId = $leagueId;

        $this->onQueue('scheduled');
    }

    /**
     * Execute the job.
     *
     * Only closed buckets are collected: the job runs once per bucket, so every stored
     * bucket is a median over its whole span and the series is uniform. A bucket that
     * has not closed yet has no prices of its own to summarize.
     *
     * The run collects the bucket that has just closed and the ones before it, oldest
     * first, so a run that failed or timed out is recovered by the next one.
     */
    public function handle(
        ExchangeMetricsServiceContract $metricsService,
    ): void {
        $bucketHours = (int) config('poe.metrics.bucket_hours');
        $buckets = max(1, (int) config('poe.metrics.buckets_to_collect'));
        $closedBucket = CarbonImmutable::now()->subHours($bucketHours);

        for ($missed = $buckets - 1; $missed >= 0; $missed--) {
            $metricsService->collectBucketMetrics(
                $this->leagueId,
                $closedBucket->subHours($bucketHours * $missed),
            );
        }
    }
}
