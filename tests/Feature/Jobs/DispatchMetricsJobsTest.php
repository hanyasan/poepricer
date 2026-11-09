<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Metrics\CollectBucketMetricsForLeagueJob;
use App\Jobs\Metrics\CollectBucketMetricsJob;
use App\Jobs\Metrics\RecalculateBetasForLeagueJob;
use App\Jobs\Metrics\RecalculateBetasJob;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Metrics\ExchangeMetricsServiceContract;
use Carbon\CarbonImmutable;
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

    public function test_league_job_collects_the_closed_bucket_and_the_one_before_it(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 00:00:01', 'UTC'));
        $collected = [];

        $this->mock(ExchangeMetricsServiceContract::class, function (MockInterface $mock) use (&$collected): void {
            $mock->shouldReceive('collectBucketMetrics')
                ->twice()
                ->andReturnUsing(static function (int $leagueId, CarbonImmutable $moment) use (&$collected): void {
                    $collected[] = [$leagueId, $moment->toDateTimeString()];
                });
        });

        $this->app->call([new CollectBucketMetricsForLeagueJob(5), 'handle']);
        CarbonImmutable::setTestNow();

        // Oldest first: the basket of a bucket is taken from the bucket before it.
        $this->assertSame([[5, '2026-11-08 00:00:01'], [5, '2026-11-08 12:00:01']], $collected);
    }

    public function test_the_number_of_collected_buckets_comes_from_the_config(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 00:00:01', 'UTC'));
        config(['poe.metrics.buckets_to_collect' => 3]);
        $collected = [];

        $this->mock(ExchangeMetricsServiceContract::class, function (MockInterface $mock) use (&$collected): void {
            $mock->shouldReceive('collectBucketMetrics')
                ->times(3)
                ->andReturnUsing(static function (int $leagueId, CarbonImmutable $moment) use (&$collected): void {
                    $collected[] = $moment->toDateTimeString();
                });
        });

        $this->app->call([new CollectBucketMetricsForLeagueJob(5), 'handle']);
        CarbonImmutable::setTestNow();

        $this->assertSame(
            ['2026-11-07 12:00:01', '2026-11-08 00:00:01', '2026-11-08 12:00:01'],
            $collected,
        );
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
