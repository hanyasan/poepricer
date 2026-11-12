<?php

namespace Tests\Feature\Services\DataServices\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\PoeLeague;
use App\Services\DataServices\Metrics\ExchangeMetricsServiceContract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class ExchangeMetricsServiceTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private PoeLeague $league;

    private ExchangeType $type;

    private ExchangeMetricsItem $divineMetrics;

    private ExchangeItem $itemA;

    private ExchangeItem $itemB;

    private ExchangeItem $itemC;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'poe.metrics.bucket_hours' => 12,
            'poe.metrics.window_buckets' => 14,
            'poe.metrics.min_residual_observations' => 3,
            'poe.metrics.min_beta_observations' => 6,
            'poe.metrics.beta_prior_weight' => 14,
        ]);

        $this->league = $this->league('Metrics Service League');
        $this->type = $this->exchangeType();
        $this->divineMetrics = $this->metricsItem($this->league, $this->item($this->league, 'divine'));
        $this->itemA = $this->item($this->league, 'item-a');
        $this->itemB = $this->item($this->league, 'item-b');
        $this->itemC = $this->item($this->league, 'item-c');
    }

    public function test_the_first_bucket_stores_prices_without_returns_or_market(): void
    {
        $this->snapshotAt('2030-01-02 01:00:00', ['item-a' => 10.0, 'item-b' => 20.0]);

        $this->collect('2030-01-02 00:00:00');

        $rows = $this->itemMetrics();

        $this->assertCount(2, $rows);
        $this->assertSame(10.0, $rows[$this->itemA->id]->price);
        $this->assertNull($rows[$this->itemA->id]->log_return);
        $this->assertNull($rows[$this->itemA->id]->residual);
        $this->assertNull($rows[$this->itemA->id]->residual_mad);
        $this->assertSame(0, $rows[$this->itemA->id]->observations);
        $this->assertSame(0, $this->marketMetricsCount($this->league));
    }

    public function test_the_price_of_a_bucket_is_the_median_of_its_snapshots(): void
    {
        $this->snapshotAt('2030-01-02 01:00:00', ['item-a' => 10.0]);
        $this->snapshotAt('2030-01-02 05:00:00', ['item-a' => 14.0]);
        $this->snapshotAt('2030-01-02 09:00:00', ['item-a' => 12.0]);

        $this->collect('2030-01-02 00:00:00');

        $this->assertSame(12.0, $this->itemMetrics()[$this->itemA->id]->price);
    }

    public function test_it_stores_the_log_return_and_the_market_median(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 11.0, 'item-b' => 10.0, 'item-c' => 9.0]);

        $this->collect('2030-01-01 12:00:00');

        $rows = $this->itemMetrics('2030-01-01 12:00:00');
        $market = ExchangeMarketMetric::query()->where('poe_league_id', $this->league->id)->sole();

        $this->assertEqualsWithDelta(log(1.1), $rows[$this->itemA->id]->log_return, 1e-12);
        $this->assertEqualsWithDelta(0.0, $rows[$this->itemB->id]->log_return, 1e-12);
        $this->assertEqualsWithDelta(log(0.9), $rows[$this->itemC->id]->log_return, 1e-12);
        $this->assertEqualsWithDelta(0.0, $market->market_return, 1e-12);
        $this->assertSame(3, $market->basket_size);
        $this->assertSame($this->league->id, $market->poe_league_id);
        $this->assertSame($this->divineMetrics->id, $market->exchange_metrics_item_id);
    }

    public function test_a_return_with_too_few_traded_units_is_kept_but_left_out_of_the_market(): void
    {
        config(['poe.metrics.min_hourly_units' => 2.0, 'poe.metrics.full_weight_hourly_units' => 20.0]);

        $this->snapshotAt(
            '2030-01-01 06:00:00',
            ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0],
            volumes: ['item-a' => 1000.0, 'item-b' => 1000.0, 'item-c' => 1000.0],
        );
        $this->snapshotAt(
            '2030-01-01 18:00:00',
            ['item-a' => 11.0, 'item-b' => 10.0, 'item-c' => 5.0],
            volumes: ['item-a' => 1000.0, 'item-b' => 1000.0, 'item-c' => 5.0],
        );

        $this->collect('2030-01-01 12:00:00');

        $rows = $this->itemMetrics('2030-01-01 12:00:00');
        $market = ExchangeMarketMetric::query()->where('poe_league_id', $this->league->id)->sole();

        $this->assertEqualsWithDelta(log(0.5), $rows[$this->itemC->id]->log_return, 1e-12);
        $this->assertSame(0.0, $rows[$this->itemC->id]->return_weight);
        $this->assertSame(1.0, $rows[$this->itemC->id]->units);
        $this->assertNull($rows[$this->itemC->id]->residual);
        $this->assertNotNull($rows[$this->itemA->id]->residual);
        $this->assertSame(2, $market->basket_size);
        $this->assertSame(3, $market->basket_total);
        $this->assertEqualsWithDelta(log(1.1) / 2.0, $market->market_return, 1e-12);
    }

    public function test_the_return_weight_comes_from_the_units_of_the_current_bucket(): void
    {
        config(['poe.metrics.min_hourly_units' => 2.0, 'poe.metrics.full_weight_hourly_units' => 20.0]);

        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0], volumes: ['item-a' => 100.0]);
        $this->snapshotAt('2030-01-01 16:00:00', ['item-a' => 10.0], volumes: ['item-a' => 100.0]);
        $this->snapshotAt('2030-01-01 17:00:00', ['item-a' => 10.0], volumes: ['item-a' => 300.0]);

        $this->collect('2030-01-01 12:00:00');

        $row = $this->itemMetrics('2030-01-01 12:00:00')[$this->itemA->id];

        // the mean of 10 and 30 units is 20, it gives the full weight even though the previous bucket has 10
        $this->assertSame(20.0, $row->units);
        $this->assertSame(1.0, $row->return_weight);
    }

    public function test_unknown_volume_keeps_the_full_weight_and_the_plain_market_median(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 11.0, 'item-b' => 10.0, 'item-c' => 9.0]);

        $this->collect('2030-01-01 12:00:00');

        $row = $this->itemMetrics('2030-01-01 12:00:00')[$this->itemA->id];
        $market = ExchangeMarketMetric::query()->where('poe_league_id', $this->league->id)->sole();

        $this->assertNull($row->units);
        $this->assertSame(1.0, $row->return_weight);
        $this->assertSame(3, $market->basket_size);
    }

    public function test_no_market_is_stored_when_every_return_is_too_thinly_traded(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0], volumes: ['item-a' => 10.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 12.0], volumes: ['item-a' => 10.0]);

        $this->collect('2030-01-01 12:00:00');

        $row = $this->itemMetrics('2030-01-01 12:00:00')[$this->itemA->id];

        $this->assertNotNull($row->log_return);
        $this->assertNull($row->residual);
        $this->assertSame(0, $this->marketMetricsCount($this->league));
    }

    public function test_the_residual_subtracts_the_market_with_the_default_beta_of_one(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 12.0, 'item-b' => 11.0, 'item-c' => 10.0]);

        $this->collect('2030-01-01 12:00:00');

        $market = log(1.1);
        $rows = $this->itemMetrics('2030-01-01 12:00:00');

        $this->assertEqualsWithDelta(log(1.2) - $market, $rows[$this->itemA->id]->residual, 1e-12);
        $this->assertEqualsWithDelta(0.0, $rows[$this->itemB->id]->residual, 1e-12);
        $this->assertEqualsWithDelta(log(1.0) - $market, $rows[$this->itemC->id]->residual, 1e-12);
    }

    public function test_the_residual_uses_the_latest_stored_beta(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 12.0, 'item-b' => 11.0, 'item-c' => 10.0]);

        ExchangeItemBeta::factory()->forItem($this->itemA, $this->divineMetrics)
            ->calculatedAt('2030-01-01 00:00:00')->withBeta(9.0)->create();
        ExchangeItemBeta::factory()->forItem($this->itemA, $this->divineMetrics)
            ->calculatedAt('2030-01-01 10:00:00')->withBeta(2.0)->create();
        ExchangeItemBeta::factory()->forItem($this->itemA, $this->divineMetrics)
            ->calculatedAt('2030-01-01 13:00:00')->withBeta(7.0)->create();

        $this->collect('2030-01-01 12:00:00');

        $this->assertEqualsWithDelta(
            log(1.2) - 2.0 * log(1.1),
            $this->itemMetrics('2030-01-01 12:00:00')[$this->itemA->id]->residual,
            1e-12,
        );
    }

    public function test_a_new_item_enters_the_basket_only_at_or_above_the_threshold(): void
    {
        ExchangeMetricsItem::query()->whereKey($this->divineMetrics->id)->update(['min_price' => 5.0]);

        $this->snapshotAt('2030-01-02 01:00:00', ['item-a' => 5.0, 'item-b' => 4.99, 'item-c' => 50.0]);

        $this->collect('2030-01-02 00:00:00');

        $this->assertEqualsCanonicalizing(
            [$this->itemA->id, $this->itemC->id],
            array_keys($this->itemMetrics()),
        );
    }

    public function test_an_item_stays_in_the_basket_after_falling_below_the_threshold(): void
    {
        ExchangeMetricsItem::query()->whereKey($this->divineMetrics->id)->update(['min_price' => 5.0]);

        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 6.0, 'item-b' => 6.0]);
        $this->collect('2030-01-01 00:00:00');

        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 3.0, 'item-b' => 6.0]);
        $this->collect('2030-01-01 12:00:00');

        $this->assertEqualsCanonicalizing(
            [$this->itemA->id, $this->itemB->id],
            array_keys($this->itemMetrics('2030-01-01 12:00:00')),
        );
        $this->assertEqualsWithDelta(
            log(0.5),
            $this->itemMetrics('2030-01-01 12:00:00')[$this->itemA->id]->log_return,
            1e-12,
        );
    }

    public function test_a_new_item_below_the_threshold_stays_out_even_if_others_are_in(): void
    {
        ExchangeMetricsItem::query()->whereKey($this->divineMetrics->id)->update(['min_price' => 5.0]);

        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 6.0, 'item-b' => 1.0]);
        $this->collect('2030-01-01 00:00:00');

        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 6.0, 'item-b' => 1.5]);
        $this->collect('2030-01-01 12:00:00');

        $this->assertSame([$this->itemA->id], array_keys($this->itemMetrics('2030-01-01 12:00:00')));
    }

    public function test_a_new_item_that_crossed_the_threshold_gets_a_return_from_the_raw_previous_prices(): void
    {
        ExchangeMetricsItem::query()->whereKey($this->divineMetrics->id)->update(['min_price' => 5.0]);

        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 4.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 6.0]);

        $this->collect('2030-01-01 12:00:00');

        $this->assertEqualsWithDelta(
            log(1.5),
            $this->itemMetrics('2030-01-01 12:00:00')[$this->itemA->id]->log_return,
            1e-12,
        );
    }

    public function test_an_item_without_a_price_in_the_bucket_leaves_the_basket(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 6.0, 'item-b' => 6.0]);
        $this->collect('2030-01-01 00:00:00');

        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 6.0]);
        $this->collect('2030-01-01 12:00:00');

        $this->assertSame([$this->itemA->id], array_keys($this->itemMetrics('2030-01-01 12:00:00')));
    }

    public function test_the_currency_of_the_metric_is_not_part_of_its_own_basket(): void
    {
        $this->snapshotAt('2030-01-02 01:00:00', ['item-a' => 1.0, 'divine' => 1.0]);

        $this->collect('2030-01-02 00:00:00');

        $this->assertSame([$this->itemA->id], array_keys($this->itemMetrics()));
    }

    public function test_prices_are_expressed_in_the_currency_of_each_metrics_item(): void
    {
        $exalted = $this->metricsItem($this->league, $this->item($this->league, 'exalted'));

        $this->snapshotAt('2030-01-02 01:00:00', ['item-a' => 0.5], ['divine' => 1.0, 'exalted' => 600.0]);

        $this->collect('2030-01-02 00:00:00');

        $inDivine = ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $this->divineMetrics->id)->sole();
        $inExalted = ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $exalted->id)->sole();

        $this->assertSame(0.5, $inDivine->price);
        $this->assertSame(300.0, $inExalted->price);
    }

    public function test_the_residual_mad_is_empty_until_enough_residuals_are_collected(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 12.0, 'item-b' => 11.0, 'item-c' => 10.0]);

        $this->collect('2030-01-01 12:00:00');

        $row = $this->itemMetrics('2030-01-01 12:00:00')[$this->itemA->id];

        $this->assertNull($row->residual_mad);
        $this->assertSame(1, $row->observations);
    }

    public function test_the_residual_mad_covers_the_stored_history_and_the_current_residual(): void
    {
        $this->storeResiduals($this->itemA, [
            '2030-01-01 00:00:00' => 0.1,
            '2030-01-01 12:00:00' => -0.1,
        ]);

        $this->snapshotAt('2030-01-02 06:00:00', ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0]);
        $this->snapshotAt('2030-01-02 18:00:00', ['item-a' => 10.0, 'item-b' => 10.0, 'item-c' => 10.0]);

        $this->collect('2030-01-02 12:00:00');

        $row = $this->itemMetrics('2030-01-02 12:00:00')[$this->itemA->id];

        // residuals [0.1, -0.1, 0.0]: median 0.0, deviations [0.1, 0.1, 0.0] -> median 0.1
        $this->assertSame(3, $row->observations);
        $this->assertEqualsWithDelta(0.14826, $row->residual_mad, 1e-9);
    }

    public function test_residuals_older_than_the_window_are_not_used(): void
    {
        $this->storeResiduals($this->itemA, [
            '2030-01-01 00:00:00' => 5.0,
            '2030-01-01 12:00:00' => 6.0,
            '2030-01-08 00:00:00' => 0.0,
        ]);

        $this->snapshotAt('2030-01-08 06:00:00', ['item-a' => 10.0]);
        $this->snapshotAt('2030-01-08 18:00:00', ['item-a' => 10.0]);

        $this->collect('2030-01-08 12:00:00');

        // only the residual stored at 2030-01-08 00:00 and the current one are inside the window
        $row = $this->itemMetrics('2030-01-08 12:00:00')[$this->itemA->id];

        $this->assertSame(2, $row->observations);
    }

    public function test_a_misaligned_start_is_floored_to_the_bucket(): void
    {
        $this->snapshotAt('2030-01-02 03:00:00', ['item-a' => 10.0]);

        $this->collect('2030-01-02 05:37:12');

        $this->assertSame([$this->itemA->id], array_keys($this->itemMetrics('2030-01-02 00:00:00')));
    }

    public function test_collecting_a_bucket_twice_does_not_duplicate_rows(): void
    {
        $this->snapshotAt('2030-01-01 06:00:00', ['item-a' => 10.0, 'item-b' => 10.0]);
        $this->snapshotAt('2030-01-01 18:00:00', ['item-a' => 11.0, 'item-b' => 10.0]);

        $this->collect('2030-01-01 12:00:00');
        $this->collect('2030-01-01 12:00:00');

        $this->assertSame(2, ExchangeItemMetric::query()->where('bucket_start', '2030-01-01 12:00:00+00')->count());
        $this->assertSame(1, $this->marketMetricsCount($this->league));
    }

    public function test_a_bucket_without_prices_stores_nothing(): void
    {
        $this->collect('2030-01-02 00:00:00');

        $this->assertSame(0, $this->itemMetricsCount($this->league));
        $this->assertSame(0, $this->marketMetricsCount($this->league));
    }

    public function test_a_league_without_metrics_items_stores_nothing(): void
    {
        $other = $this->league('No Metrics League');
        $this->item($other, 'item-a');
        $this->snapshot($other, $this->type, '2030-01-02 01:00:00', ['item-a' => 1.0]);

        $this->service()->collectBucketMetrics($other->id, CarbonImmutable::parse('2030-01-02 00:00:00', 'UTC'));

        $this->assertSame(0, $this->itemMetricsCount($other));
    }

    public function test_it_calculates_the_beta_and_r_squared_over_the_window(): void
    {
        $marketReturns = [0.01, -0.02, 0.03, -0.01, 0.02, -0.03, 0.015, -0.005];
        $this->storeReturns($this->itemA, '2030-01-04 00:00:00', $marketReturns, 2.0);

        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 00:00:00'));

        $beta = $this->betas()->sole();
        $weight = 8 / (8 + 14);

        $this->assertSame($this->itemA->id, $beta->exchange_item_id);
        $this->assertSame($this->divineMetrics->id, $beta->exchange_metrics_item_id);
        $this->assertEqualsWithDelta(2.0, $beta->raw_beta, 1e-9);
        $this->assertEqualsWithDelta($weight * 2.0 + (1 - $weight), $beta->beta, 1e-9);
        $this->assertEqualsWithDelta(1.0, $beta->r_squared, 1e-9);
        $this->assertSame(8, $beta->observations);
        $this->assertSame('2030-01-08 00:00:00+00', $beta->calculated_at);
    }

    public function test_it_skips_items_with_too_few_observations(): void
    {
        $this->storeReturns($this->itemA, '2030-01-04 00:00:00', [0.01, -0.02, 0.03, -0.01, 0.02], 2.0);

        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 00:00:00'));

        $this->assertSame(0, $this->betas()->count());
    }

    public function test_it_skips_items_when_the_market_did_not_move(): void
    {
        $this->storeReturns($this->itemA, '2030-01-04 00:00:00', array_fill(0, 8, 0.0), 2.0);

        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 00:00:00'));

        $this->assertSame(0, $this->betas()->count());
    }

    public function test_it_ignores_returns_older_than_the_window(): void
    {
        $this->storeReturns($this->itemA, '2029-12-20 00:00:00', [0.01, -0.02, 0.03, -0.01, 0.02, -0.03, 0.015, -0.005], 2.0);

        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 00:00:00'));

        $this->assertSame(0, $this->betas()->count());
    }

    public function test_recalculating_at_the_same_moment_keeps_one_record(): void
    {
        $this->storeReturns($this->itemA, '2030-01-04 00:00:00', [0.01, -0.02, 0.03, -0.01, 0.02, -0.03, 0.015, -0.005], 2.0);

        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 00:00:00'));
        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 00:00:00'));

        $this->assertSame(1, $this->betas()->count());
    }

    public function test_recalculating_later_keeps_the_history(): void
    {
        $this->storeReturns($this->itemA, '2030-01-04 00:00:00', [0.01, -0.02, 0.03, -0.01, 0.02, -0.03, 0.015, -0.005], 2.0);

        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 00:00:00'));
        $this->service()->recalculateBetas($this->league->id, $this->at('2030-01-08 12:00:00'));

        $this->assertSame(2, $this->betas()->count());
    }

    /**
     * @param  array<string, float>  $rates
     * @param  array<string, float>  $coreRates
     * @param  array<string, float>  $volumes
     */
    private function snapshotAt(string $createdAt, array $rates, array $coreRates = ['divine' => 1.0], array $volumes = []): void
    {
        $this->snapshot($this->league, $this->type, $createdAt, $rates, $coreRates, $volumes);
    }

    private function collect(string $bucketStart): void
    {
        $this->service()->collectBucketMetrics($this->league->id, $this->at($bucketStart));
    }

    /**
     * @return array<int, ExchangeItemMetric> keyed by item id
     */
    private function itemMetricsCount(PoeLeague $league): int
    {
        return ExchangeItemMetric::query()
            ->whereIn('exchange_item_id', ExchangeItem::query()->where('poe_league_id', $league->id)->select('id'))
            ->count();
    }

    private function marketMetricsCount(PoeLeague $league): int
    {
        return ExchangeMarketMetric::query()->where('poe_league_id', $league->id)->count();
    }

    /**
     * Betas of the metrics item under test, the database also holds rows of the other leagues.
     *
     * @return Builder<ExchangeItemBeta>
     */
    private function betas(): Builder
    {
        return ExchangeItemBeta::query()->where('exchange_metrics_item_id', $this->divineMetrics->id);
    }

    private function itemMetrics(?string $bucketStart = null): array
    {
        return ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $this->divineMetrics->id)
            ->when($bucketStart !== null, fn ($query) => $query->where('bucket_start', $bucketStart.'+00'))
            ->get()
            ->keyBy('exchange_item_id')
            ->all();
    }

    /**
     * @param  array<string, float>  $residualsByBucket
     */
    private function storeResiduals(ExchangeItem $item, array $residualsByBucket): void
    {
        foreach ($residualsByBucket as $bucket => $residual) {
            ExchangeItemMetric::factory()
                ->forItem($item, $this->divineMetrics)
                ->atBucket($bucket)
                ->state(['residual' => $residual])
                ->create();
        }
    }

    /**
     * Stores consecutive 12 hour buckets in which the item moves `$beta` times the market.
     *
     * @param  array<int, float>  $marketReturns
     */
    private function storeReturns(ExchangeItem $item, string $firstBucket, array $marketReturns, float $beta): void
    {
        $bucket = $this->at($firstBucket);

        foreach ($marketReturns as $marketReturn) {
            ExchangeMarketMetric::factory()
                ->forMetricsItem($this->divineMetrics)
                ->atBucket($bucket)
                ->withReturn($marketReturn)
                ->create();

            ExchangeItemMetric::factory()
                ->forItem($item, $this->divineMetrics)
                ->atBucket($bucket)
                ->withReturn($beta * $marketReturn)
                ->create();

            $bucket = $bucket->addHours(12);
        }
    }

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment, 'UTC');
    }

    private function service(): ExchangeMetricsServiceContract
    {
        return $this->app->make(ExchangeMetricsServiceContract::class);
    }
}
