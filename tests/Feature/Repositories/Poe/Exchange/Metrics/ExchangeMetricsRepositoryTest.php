<?php

namespace Tests\Feature\Repositories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class ExchangeMetricsRepositoryTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    public function test_basket_item_ids_are_limited_to_the_bucket_and_the_metrics_item(): void
    {
        [$metricsItem, $otherMetricsItem, $itemA, $itemB] = $this->twoMetricsItemsAndTwoItems('Basket League');

        ExchangeItemMetric::factory()->forItem($itemA, $metricsItem)->atBucket('2030-01-01 00:00:00')->create();
        ExchangeItemMetric::factory()->forItem($itemB, $metricsItem)->atBucket('2030-01-01 12:00:00')->create();
        ExchangeItemMetric::factory()->forItem($itemB, $otherMetricsItem)->atBucket('2030-01-01 00:00:00')->create();

        $ids = $this->repository()->getBasketItemIds($metricsItem->id, $this->at('2030-01-01 00:00:00'));

        $this->assertSame([$itemA->id], $ids);
    }

    public function test_upsert_item_metrics_inserts_and_then_updates_the_same_key(): void
    {
        [$metricsItem, , $item] = $this->twoMetricsItemsAndTwoItems('Upsert Item League');
        $row = [
            'exchange_item_id' => $item->id,
            'exchange_metrics_item_id' => $metricsItem->id,
            'bucket_start' => '2030-01-01 00:00:00+00:00',
            'price' => 2.0,
            'log_return' => null,
            'residual' => null,
            'residual_mad' => null,
            'observations' => 0,
        ];

        $this->repository()->upsertItemMetrics([$row]);
        $this->repository()->upsertItemMetrics([[...$row, 'price' => 3.0, 'log_return' => 0.5, 'observations' => 2]]);

        $stored = ExchangeItemMetric::query()->where('exchange_item_id', $item->id)->get();

        $this->assertCount(1, $stored);
        $this->assertSame(3.0, $stored[0]->price);
        $this->assertSame(0.5, $stored[0]->log_return);
        $this->assertSame(2, $stored[0]->observations);
    }

    public function test_upsert_item_metrics_with_no_rows_stores_nothing(): void
    {
        $this->repository()->upsertItemMetrics([]);

        $this->assertSame(0, ExchangeItemMetric::query()->count());
    }

    public function test_upsert_market_metric_updates_the_same_bucket(): void
    {
        [$metricsItem] = $this->twoMetricsItemsAndTwoItems('Upsert Market League');
        $row = [
            'poe_league_id' => $metricsItem->poe_league_id,
            'exchange_metrics_item_id' => $metricsItem->id,
            'bucket_start' => '2030-01-01 00:00:00+00:00',
            'market_return' => 0.1,
            'basket_size' => 3,
        ];

        $this->repository()->upsertMarketMetric($row);
        $this->repository()->upsertMarketMetric([...$row, 'market_return' => -0.2, 'basket_size' => 4]);

        $stored = ExchangeMarketMetric::query()->where('exchange_metrics_item_id', $metricsItem->id)->get();

        $this->assertCount(1, $stored);
        $this->assertSame(-0.2, $stored[0]->market_return);
        $this->assertSame(4, $stored[0]->basket_size);
    }

    public function test_latest_betas_pick_the_newest_value_at_or_before_the_moment_per_item(): void
    {
        [$metricsItem, $otherMetricsItem, $itemA, $itemB] = $this->twoMetricsItemsAndTwoItems('Beta League');

        $this->beta($itemA, $metricsItem, '2030-01-01 00:00:00', 1.1);
        $this->beta($itemA, $metricsItem, '2030-01-02 00:00:00', 1.2);
        $this->beta($itemA, $metricsItem, '2030-01-03 00:00:00', 1.3);
        $this->beta($itemB, $metricsItem, '2030-01-03 00:00:00', 2.0);
        $this->beta($itemA, $otherMetricsItem, '2030-01-02 00:00:00', 9.0);

        $betas = $this->repository()->getLatestBetas($metricsItem->id, $this->at('2030-01-02 12:00:00'));

        $this->assertSame([$itemA->id => 1.2], $betas);
    }

    public function test_latest_betas_include_a_beta_calculated_exactly_at_the_moment(): void
    {
        [$metricsItem, , $item] = $this->twoMetricsItemsAndTwoItems('Beta Border League');

        $this->beta($item, $metricsItem, '2030-01-02 00:00:00', 1.5);

        $betas = $this->repository()->getLatestBetas($metricsItem->id, $this->at('2030-01-02 00:00:00'));

        $this->assertSame([$item->id => 1.5], $betas);
    }

    public function test_residual_history_is_limited_to_the_period_skips_missing_residuals_and_is_ordered(): void
    {
        [$metricsItem, , $item] = $this->twoMetricsItemsAndTwoItems('Residual League');

        $this->itemMetric($item, $metricsItem, '2029-12-31 12:00:00', residual: 9.0);
        $this->itemMetric($item, $metricsItem, '2030-01-01 12:00:00', residual: 0.3);
        $this->itemMetric($item, $metricsItem, '2030-01-01 00:00:00', residual: 0.1);
        $this->itemMetric($item, $metricsItem, '2030-01-02 00:00:00', residual: null);
        $this->itemMetric($item, $metricsItem, '2030-01-02 12:00:00', residual: 7.0);

        $history = $this->repository()->getResidualHistory(
            $metricsItem->id,
            $this->at('2030-01-01 00:00:00'),
            $this->at('2030-01-02 12:00:00'),
        );

        $this->assertSame([$item->id => [0.1, 0.3]], $history);
    }

    public function test_return_pairs_join_the_market_return_of_the_same_bucket(): void
    {
        [$metricsItem, , $itemA, $itemB] = $this->twoMetricsItemsAndTwoItems('Pairs League');

        foreach ([['2030-01-01 00:00:00', 0.1], ['2030-01-01 12:00:00', -0.2]] as [$bucket, $market]) {
            ExchangeMarketMetric::factory()->forMetricsItem($metricsItem)->atBucket($bucket)->withReturn($market)->create();
        }

        $this->itemMetric($itemA, $metricsItem, '2030-01-01 12:00:00', logReturn: -0.4);
        $this->itemMetric($itemA, $metricsItem, '2030-01-01 00:00:00', logReturn: 0.3);
        $this->itemMetric($itemB, $metricsItem, '2030-01-01 00:00:00', logReturn: 0.5);

        $pairs = $this->repository()->getReturnPairs(
            $metricsItem->id,
            $this->at('2030-01-01 00:00:00'),
            $this->at('2030-01-02 00:00:00'),
        );

        $this->assertSame([
            $itemA->id => [['market' => 0.1, 'item' => 0.3], ['market' => -0.2, 'item' => -0.4]],
            $itemB->id => [['market' => 0.1, 'item' => 0.5]],
        ], $pairs);
    }

    public function test_return_pairs_skip_items_without_a_return_or_a_market_row_and_other_periods(): void
    {
        [$metricsItem, , $item] = $this->twoMetricsItemsAndTwoItems('Pairs Skip League');

        ExchangeMarketMetric::factory()->forMetricsItem($metricsItem)->atBucket('2030-01-01 00:00:00')->create();
        ExchangeMarketMetric::factory()->forMetricsItem($metricsItem)->atBucket('2030-01-03 00:00:00')->create();

        $this->itemMetric($item, $metricsItem, '2030-01-01 00:00:00', logReturn: null);
        $this->itemMetric($item, $metricsItem, '2030-01-01 12:00:00', logReturn: 0.2);
        $this->itemMetric($item, $metricsItem, '2030-01-03 00:00:00', logReturn: 0.3);

        $pairs = $this->repository()->getReturnPairs(
            $metricsItem->id,
            $this->at('2030-01-01 00:00:00'),
            $this->at('2030-01-02 00:00:00'),
        );

        $this->assertSame([], $pairs);
    }

    public function test_upsert_betas_updates_the_same_calculation_moment(): void
    {
        [$metricsItem, , $item] = $this->twoMetricsItemsAndTwoItems('Upsert Beta League');
        $row = [
            'exchange_item_id' => $item->id,
            'exchange_metrics_item_id' => $metricsItem->id,
            'calculated_at' => '2030-01-01 00:00:00+00:00',
            'beta' => 1.5,
            'raw_beta' => 2.0,
            'r_squared' => 0.4,
            'observations' => 10,
        ];

        $this->repository()->upsertBetas([$row]);
        $this->repository()->upsertBetas([[...$row, 'beta' => 1.7, 'r_squared' => null, 'observations' => 12]]);

        $stored = ExchangeItemBeta::query()->where('exchange_item_id', $item->id)->get();

        $this->assertCount(1, $stored);
        $this->assertSame(1.7, $stored[0]->beta);
        $this->assertNull($stored[0]->r_squared);
        $this->assertSame(12, $stored[0]->observations);
    }

    private function repository(): ExchangeMetricsRepositoryContract
    {
        return $this->app->make(ExchangeMetricsRepositoryContract::class);
    }

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment, 'UTC');
    }

    /**
     * @return array{0: ExchangeMetricsItem, 1: ExchangeMetricsItem, 2: ExchangeItem, 3: ExchangeItem}
     */
    private function twoMetricsItemsAndTwoItems(string $leagueName): array
    {
        $league = $this->league($leagueName);

        return [
            $this->metricsItem($league, $this->item($league, 'divine')),
            $this->metricsItem($league, $this->item($league, 'chaos')),
            $this->item($league, 'item-a'),
            $this->item($league, 'item-b'),
        ];
    }

    private function beta(
        ExchangeItem $item,
        ExchangeMetricsItem $metricsItem,
        string $calculatedAt,
        float $beta,
    ): void {
        ExchangeItemBeta::factory()
            ->forItem($item, $metricsItem)
            ->calculatedAt($calculatedAt)
            ->withBeta($beta)
            ->create();
    }

    private function itemMetric(
        ExchangeItem $item,
        ExchangeMetricsItem $metricsItem,
        string $bucketStart,
        ?float $residual = null,
        ?float $logReturn = null,
    ): void {
        ExchangeItemMetric::factory()
            ->forItem($item, $metricsItem)
            ->atBucket($bucketStart)
            ->state(['residual' => $residual, 'log_return' => $logReturn])
            ->create();
    }
}
