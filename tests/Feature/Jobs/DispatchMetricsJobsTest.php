<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Metrics\CollectBucketMetricsForLeagueJob;
use App\Jobs\Metrics\CollectBucketMetricsJob;
use App\Jobs\Metrics\RecalculateBetasForLeagueJob;
use App\Jobs\Metrics\RecalculateBetasJob;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\FeatureTestCase;

class DispatchMetricsJobsTest extends FeatureTestCase
{
    public function test_collect_bucket_metrics_job_dispatches_a_job_per_league(): void
    {
        Queue::fake();
        $this->mockLeagueIds([3, 7]);

        $this->app->call([new CollectBucketMetricsJob, 'handle']);

        Queue::assertPushed(CollectBucketMetricsForLeagueJob::class, 2);
    }

    public function test_recalculate_betas_job_dispatches_a_job_per_league(): void
    {
        Queue::fake();
        $this->mockLeagueIds([3, 7]);

        $this->app->call([new RecalculateBetasJob, 'handle']);

        Queue::assertPushed(RecalculateBetasForLeagueJob::class, 2);
    }

    public function test_nothing_is_dispatched_without_leagues(): void
    {
        Queue::fake();
        $this->mockLeagueIds([]);

        $this->app->call([new CollectBucketMetricsJob, 'handle']);
        $this->app->call([new RecalculateBetasJob, 'handle']);

        Queue::assertNothingPushed();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function mockLeagueIds(array $ids): void
    {
        $this->mock(PoeLeagueRepositoryContract::class, function (MockInterface $mock) use ($ids): void {
            $mock->shouldReceive('getActiveIdsWithMetricsItems')->andReturn($ids);
        });
    }
}
