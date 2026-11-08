<?php

namespace Tests\Feature;

use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ExchangePriceTagEnum;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\PoeLeague;
use App\Services\DataServices\Metrics\ExchangeMetricsItemSeedServiceContract;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class LeagueOverviewRouteFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private const string LAST_BUCKET = '2030-01-10 12:00:00';

    public function test_unknown_league_is_not_found(): void
    {
        $this->getJson('/api/leagues/no-such-league/currencies')->assertNotFound();
        $this->getJson('/api/leagues/no-such-league/overview')->assertNotFound();
    }

    public function test_inactive_league_is_not_found(): void
    {
        $league = $this->league('Overview Inactive League');
        $league->update(['is_active' => false]);

        $this->getJson("/api/leagues/{$league->slug}/currencies")->assertNotFound();
        $this->getJson("/api/leagues/{$league->slug}/overview")->assertNotFound();
    }

    public function test_currencies_are_listed_with_the_default_currency_first(): void
    {
        $league = $this->league('Overview Currencies League');
        $this->metricsItem($league, $this->item($league, 'chaos'));
        $this->metricsItem($league, $this->item($league, 'mirror'));
        $this->metricsItem($league, $this->item($league, 'divine'));

        $this->getJson("/api/leagues/{$league->slug}/currencies")
            ->assertOk()
            ->assertExactJson([
                ['slug' => 'divine', 'name' => 'DIVINE'],
                ['slug' => 'chaos', 'name' => 'CHAOS'],
                ['slug' => 'mirror', 'name' => 'MIRROR'],
            ]);
    }

    public function test_currencies_of_a_league_without_metrics_items_are_empty(): void
    {
        $league = $this->league('Overview No Currencies League');

        $this->getJson("/api/leagues/{$league->slug}/currencies")->assertOk()->assertExactJson([]);
    }

    public function test_overview_has_the_documented_shape_and_numeric_values(): void
    {
        [$league] = $this->leagueWithBuckets('Overview Shape League', [
            ['2030-01-10 00:00:00', 0.01, 30],
            [self::LAST_BUCKET, -0.0048, 42],
        ]);
        $this->snapshot($league, $this->exchangeType('overview-shape-type'), '2030-01-10 12:10:00', ['divine' => 1.0]);

        $json = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json();

        $this->assertSame(
            ['currency', 'period', 'lastBucket', 'kpi', 'marketReturnSeries', 'unusualMoves', 'topGainers', 'topLosers'],
            array_keys($json),
        );
        $this->assertSame('divine', $json['currency']);
        $this->assertSame('7d', $json['period']);
        $this->assertSame('2030-01-10T12:00:00+00:00', $json['lastBucket']);
        $this->assertSame(
            ['marketReturnLastBucket' => -0.0048, 'marketReturn7d' => 0.0052, 'basketSize' => 42, 'basketTotal' => 42, 'lastSnapshotAt' => '2030-01-10T12:10:00+00:00'],
            [...$json['kpi'], 'marketReturn7d' => round($json['kpi']['marketReturn7d'], 6)],
        );
        $this->assertSame(
            [
                ['bucket' => '2030-01-10T00:00:00+00:00', 'value' => 0.01],
                ['bucket' => '2030-01-10T12:00:00+00:00', 'value' => -0.0048],
            ],
            $json['marketReturnSeries'],
        );
    }

    public function test_period_limits_the_series_but_not_the_seven_day_kpi(): void
    {
        [$league] = $this->leagueWithBuckets('Overview Period League', [
            ['2030-01-01 00:00:00', 0.5, 10],
            ['2030-01-08 12:00:00', 0.1, 10],
            ['2030-01-10 00:00:00', 0.2, 10],
            [self::LAST_BUCKET, 0.3, 10],
        ]);

        $day = $this->getJson("/api/leagues/{$league->slug}/overview?period=24h")->assertOk()->json();
        $week = $this->getJson("/api/leagues/{$league->slug}/overview?period=7d")->assertOk()->json();
        $all = $this->getJson("/api/leagues/{$league->slug}/overview?period=league")->assertOk()->json();

        $this->assertSame([0.2, 0.3], array_column($day['marketReturnSeries'], 'value'));
        $this->assertSame([0.1, 0.2, 0.3], array_column($week['marketReturnSeries'], 'value'));
        $this->assertSame([0.5, 0.1, 0.2, 0.3], array_column($all['marketReturnSeries'], 'value'));

        foreach ([$day, $week, $all] as $overview) {
            $this->assertEqualsWithDelta(0.6, $overview['kpi']['marketReturn7d'], 1e-9);
            $this->assertSame(0.3, $overview['kpi']['marketReturnLastBucket']);
        }
    }

    public function test_top_gainers_and_losers_come_from_the_last_bucket(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Tops League', [[self::LAST_BUCKET, 0.0, 5]]);
        $this->itemMetric($league, $metricsItem, 'up-small', self::LAST_BUCKET, 0.02);
        $this->itemMetric($league, $metricsItem, 'up-big', self::LAST_BUCKET, 0.2);
        $this->itemMetric($league, $metricsItem, 'down-small', self::LAST_BUCKET, -0.03);
        $this->itemMetric($league, $metricsItem, 'down-big', self::LAST_BUCKET, -0.3);
        $this->itemMetric($league, $metricsItem, 'flat', self::LAST_BUCKET, 0.0);
        $this->itemMetric($league, $metricsItem, 'no-return', self::LAST_BUCKET, null);
        $this->itemMetric($league, $metricsItem, 'old-bucket', '2030-01-10 00:00:00', 0.9);

        $json = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json();

        $this->assertSame(['up-big', 'up-small'], array_column($json['topGainers'], 'slug'));
        $this->assertSame(['down-big', 'down-small'], array_column($json['topLosers'], 'slug'));
        $this->assertEquals(
            ['slug' => 'up-big', 'name' => 'UP-BIG', 'price' => 1.0, 'logReturn' => 0.2],
            $json['topGainers'][0],
        );
    }

    public function test_tops_are_limited_by_config(): void
    {
        config(['poe.overview.top_limit' => 2]);
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Limit League', [[self::LAST_BUCKET, 0.0, 5]]);

        foreach ([0.01, 0.02, 0.03] as $index => $logReturn) {
            $this->itemMetric($league, $metricsItem, "gain-{$index}", self::LAST_BUCKET, $logReturn);
        }

        $json = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json();

        $this->assertSame(['gain-2', 'gain-1'], array_column($json['topGainers'], 'slug'));
    }

    public function test_unusual_moves_exceed_the_residual_mad_threshold_and_are_ordered_by_ratio(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Unusual League', [[self::LAST_BUCKET, 0.0, 5]]);
        $this->itemMetric($league, $metricsItem, 'ratio-3', self::LAST_BUCKET, 0.03, 0.03, 0.01, 14);
        $this->itemMetric($league, $metricsItem, 'ratio-5-negative', self::LAST_BUCKET, -0.05, -0.05, 0.01, 10);
        $this->itemMetric($league, $metricsItem, 'ratio-2-exact', self::LAST_BUCKET, 0.02, 0.02, 0.01, 14);
        $this->itemMetric($league, $metricsItem, 'ratio-1', self::LAST_BUCKET, 0.01, 0.01, 0.01, 14);
        $this->itemMetric($league, $metricsItem, 'no-mad', self::LAST_BUCKET, 0.5, 0.5, null, 2);
        $this->itemMetric($league, $metricsItem, 'zero-mad', self::LAST_BUCKET, 0.5, 0.5, 0.0, 14);
        $this->itemMetric($league, $metricsItem, 'old-bucket', '2030-01-10 00:00:00', 0.5, 0.5, 0.01, 14);

        $moves = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json('unusualMoves');

        $this->assertSame(['ratio-5-negative', 'ratio-3'], array_column($moves, 'slug'));
        $this->assertEqualsWithDelta(-5.0, $moves[0]['ratio'], 1e-9);
        $this->assertSame(10, $moves[0]['observations']);
        $this->assertSame(
            ['slug', 'name', 'price', 'logReturn', 'residual', 'residualMad', 'ratio', 'beta', 'observations', 'units', 'returnWeight'],
            array_keys($moves[0]),
        );
    }

    public function test_unusual_moves_carry_the_latest_beta_known_at_the_bucket(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Beta League', [[self::LAST_BUCKET, 0.0, 5]]);
        $this->itemMetric($league, $metricsItem, 'with-beta', self::LAST_BUCKET, 0.05, 0.05, 0.01, 14);
        $this->itemMetric($league, $metricsItem, 'without-beta', self::LAST_BUCKET, 0.05, 0.05, 0.01, 14);
        $item = ExchangeItem::query()->where('poe_league_id', $league->id)->where('trade_id', 'with-beta')->firstOrFail();

        foreach ([['2030-01-08 00:00:00', 1.2], ['2030-01-10 00:00:00', 1.7], ['2030-01-11 00:00:00', 9.9]] as [$calculatedAt, $beta]) {
            ExchangeItemBeta::factory()->forItem($item, $metricsItem)->calculatedAt($calculatedAt)->withBeta($beta)->create();
        }

        $moves = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json('unusualMoves');
        $betas = array_column($moves, 'beta', 'slug');

        $this->assertEqualsWithDelta(1.7, $betas['with-beta'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $betas['without-beta'], 1e-9);
    }

    public function test_overview_is_measured_in_the_requested_currency(): void
    {
        [$league] = $this->leagueWithBuckets('Overview Currency League', [[self::LAST_BUCKET, 0.01, 5]]);
        $mirror = $this->metricsItem($league, $this->item($league, 'mirror'));
        ExchangeMarketMetric::factory()->forMetricsItem($mirror)->atBucket(self::LAST_BUCKET)->withReturn(-0.4)->create();

        $json = $this->getJson("/api/leagues/{$league->slug}/overview?currency=mirror")->assertOk()->json();

        $this->assertSame('mirror', $json['currency']);
        $this->assertSame(-0.4, $json['kpi']['marketReturnLastBucket']);
    }

    public function test_default_currency_is_used_when_none_is_given(): void
    {
        config(['poe.base.default_main_trade_id' => 'chaos']);
        $league = $this->league('Overview Default Currency League');
        $this->metricsItem($league, $this->item($league, 'divine'));
        $this->metricsItem($league, $this->item($league, 'chaos'));

        $this->assertSame('chaos', $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json('currency'));
    }

    public function test_first_currency_is_used_when_the_default_is_not_a_league_currency(): void
    {
        $league = $this->league('Overview Fallback Currency League');
        $this->metricsItem($league, $this->item($league, 'exalted'));

        $this->assertSame('exalted', $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json('currency'));
    }

    public function test_unknown_currency_is_rejected(): void
    {
        [$league] = $this->leagueWithBuckets('Overview Unknown Currency League', [[self::LAST_BUCKET, 0.01, 5]]);

        $this->getJson("/api/leagues/{$league->slug}/overview?currency=mirror")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_invalid_period_is_rejected(): void
    {
        [$league] = $this->leagueWithBuckets('Overview Bad Period League', [[self::LAST_BUCKET, 0.01, 5]]);

        $this->getJson("/api/leagues/{$league->slug}/overview?period=1y")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_league_without_metrics_items_gets_an_empty_overview(): void
    {
        $league = $this->league('Overview Empty League');

        $this->getJson("/api/leagues/{$league->slug}/overview")
            ->assertOk()
            ->assertExactJson([
                'currency' => null,
                'period' => '7d',
                'lastBucket' => null,
                'kpi' => ['marketReturnLastBucket' => null, 'marketReturn7d' => null, 'basketSize' => 0, 'basketTotal' => 0, 'lastSnapshotAt' => null],
                'marketReturnSeries' => [],
                'unusualMoves' => [],
                'topGainers' => [],
                'topLosers' => [],
            ]);
    }

    public function test_currency_without_market_metrics_gets_an_empty_overview(): void
    {
        $league = $this->league('Overview No Buckets League');
        $this->metricsItem($league, $this->item($league, 'divine'));

        $json = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json();

        $this->assertSame('divine', $json['currency']);
        $this->assertNull($json['lastBucket']);
        $this->assertSame([], $json['marketReturnSeries']);
        $this->assertSame(0, $json['kpi']['basketSize']);
        $this->assertSame(0, $json['kpi']['basketTotal']);
    }

    public function test_the_basket_total_is_reported_next_to_the_reliable_basket_size(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Basket League', [[self::LAST_BUCKET, 0.0, 5]]);
        ExchangeMarketMetric::query()->where('exchange_metrics_item_id', $metricsItem->id)->update(['basket_total' => 50]);

        $kpi = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json('kpi');

        $this->assertSame(5, $kpi['basketSize']);
        $this->assertSame(50, $kpi['basketTotal']);
    }

    public function test_tops_leave_out_returns_of_thinly_traded_items(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Weight Tops League', [[self::LAST_BUCKET, 0.0, 5]]);
        $this->itemMetric($league, $metricsItem, 'thin-up', self::LAST_BUCKET, 0.9, returnWeight: 0.0);
        $this->itemMetric($league, $metricsItem, 'thin-down', self::LAST_BUCKET, -0.9, returnWeight: 0.0);
        $this->itemMetric($league, $metricsItem, 'partial-up', self::LAST_BUCKET, 0.1, returnWeight: 0.3);
        $this->itemMetric($league, $metricsItem, 'unknown-down', self::LAST_BUCKET, -0.1, returnWeight: null);

        $json = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json();

        $this->assertSame(['partial-up'], array_column($json['topGainers'], 'slug'));
        $this->assertSame(['unknown-down'], array_column($json['topLosers'], 'slug'));
    }

    public function test_unusual_moves_carry_the_traded_units_and_the_return_weight(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Units League', [[self::LAST_BUCKET, 0.0, 5]]);
        $this->itemMetric($league, $metricsItem, 'known', self::LAST_BUCKET, 0.05, 0.05, 0.01, 14, units: 12.5, returnWeight: 0.5);
        $this->itemMetric($league, $metricsItem, 'unknown', self::LAST_BUCKET, 0.04, 0.04, 0.01, 14);

        $moves = $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk()->json('unusualMoves');
        $bySlug = array_column($moves, null, 'slug');

        $this->assertSame(12.5, $bySlug['known']['units']);
        $this->assertSame(0.5, $bySlug['known']['returnWeight']);
        $this->assertNull($bySlug['unknown']['units']);
        $this->assertNull($bySlug['unknown']['returnWeight']);
    }

    public function test_the_overview_is_cached_between_requests(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Cached League', [[self::LAST_BUCKET, 0.01, 5]]);

        $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk();
        $this->itemMetric($league, $metricsItem, 'late-gainer', self::LAST_BUCKET, 0.4);

        $this->assertSame([], $this->getJson("/api/leagues/{$league->slug}/overview")->json('topGainers'));
    }

    /**
     * @return array<string, array{0: array<int, string>}>
     */
    public static function overviewInvalidationTags(): array
    {
        return [
            'new buckets' => [[BucketMetricsTagEnum::bucket_metrics_league_id->name]],
            'recalculated betas' => [[BetasMetricsTagEnum::betas_metrics_league_id->name]],
            'imported prices' => [[ExchangePriceTagEnum::price_league_id->name]],
        ];
    }

    /**
     * @param  array<int, string>  $tagNames
     */
    #[DataProvider('overviewInvalidationTags')]
    public function test_flushing_a_league_tag_drops_the_cached_overview(array $tagNames): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Flush League', [[self::LAST_BUCKET, 0.01, 5]]);
        $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk();
        $this->itemMetric($league, $metricsItem, 'late-gainer', self::LAST_BUCKET, 0.4);

        Cache::tags([$tagNames[0].$league->id])->flush();

        $this->assertSame(
            ['late-gainer'],
            array_column($this->getJson("/api/leagues/{$league->slug}/overview")->json('topGainers'), 'slug'),
        );
    }

    public function test_flushing_another_league_does_not_drop_the_cached_overview(): void
    {
        [$league, $metricsItem] = $this->leagueWithBuckets('Overview Kept League', [[self::LAST_BUCKET, 0.01, 5]]);
        [$other] = $this->leagueWithBuckets('Overview Other League', [[self::LAST_BUCKET, 0.01, 5]]);
        $this->getJson("/api/leagues/{$league->slug}/overview")->assertOk();
        $this->itemMetric($league, $metricsItem, 'late-gainer', self::LAST_BUCKET, 0.4);

        Cache::tags([
            BucketMetricsTagEnum::bucket_metrics_league_id->name.$other->id,
            ExchangePriceTagEnum::price_league_id->name.$other->id,
        ])->flush();

        $this->assertSame([], $this->getJson("/api/leagues/{$league->slug}/overview")->json('topGainers'));
    }

    public function test_currencies_are_cached_and_dropped_when_a_currency_is_mixed_in(): void
    {
        $league = $this->league('Overview Mix League');
        $this->metricsItem($league, $this->item($league, 'divine'));
        $this->item($league, 'mirror');
        $this->item($league, 'item-a');
        $this->snapshot(
            $league,
            $this->exchangeType('overview-mix-type'),
            '2030-01-10 00:00:00+00',
            ['mirror' => 10.0, 'item-a' => 5.0],
        );

        $this->assertCount(1, $this->getJson("/api/leagues/{$league->slug}/currencies")->json());

        $result = $this->app->make(ExchangeMetricsItemSeedServiceContract::class)->mixItemForLeague($league->id, 'mirror');

        $this->assertSame('Added', $result->status->name);
        $this->assertSame(
            ['divine', 'mirror'],
            array_column($this->getJson("/api/leagues/{$league->slug}/currencies")->json(), 'slug'),
        );
    }

    public function test_currencies_are_dropped_when_the_league_is_seeded(): void
    {
        $league = $this->league('Overview Seed League');
        $this->item($league, 'divine');
        $this->item($league, 'item-a');
        $this->snapshot($league, $this->exchangeType('overview-seed-type'), '2030-01-10 00:00:00+00', ['item-a' => 5.0]);

        $this->assertSame([], $this->getJson("/api/leagues/{$league->slug}/currencies")->json());

        $this->app->make(ExchangeMetricsItemSeedServiceContract::class)->seedForLeague($league->id);

        $this->assertSame(['divine'], array_column($this->getJson("/api/leagues/{$league->slug}/currencies")->json(), 'slug'));
    }

    public function test_flushing_the_league_tag_drops_the_cached_league_lookup(): void
    {
        $league = $this->league('Overview Deactivated League');
        $this->getJson("/api/leagues/{$league->slug}/currencies")->assertOk();
        $league->update(['is_active' => false]);

        $this->getJson("/api/leagues/{$league->slug}/currencies")->assertOk();

        Cache::tags([PoeLeagueTagEnum::league->name])->flush();

        $this->getJson("/api/leagues/{$league->slug}/currencies")->assertNotFound();
    }

    /**
     * @param  array<int, array{0: string, 1: float, 2: int}>  $buckets  bucket start, market return, basket size
     * @return array{0: PoeLeague, 1: ExchangeMetricsItem}
     */
    private function leagueWithBuckets(string $name, array $buckets): array
    {
        $league = $this->league($name);
        $metricsItem = $this->metricsItem($league, $this->item($league, 'divine'));

        foreach ($buckets as [$bucketStart, $marketReturn, $basketSize]) {
            ExchangeMarketMetric::factory()
                ->forMetricsItem($metricsItem)
                ->atBucket($bucketStart)
                ->withReturn($marketReturn)
                ->state(['basket_size' => $basketSize])
                ->create();
        }

        return [$league, $metricsItem];
    }

    private function itemMetric(
        PoeLeague $league,
        ExchangeMetricsItem $metricsItem,
        string $tradeId,
        string $bucketStart,
        ?float $logReturn,
        ?float $residual = null,
        ?float $residualMad = null,
        int $observations = 0,
        ?float $units = null,
        ?float $returnWeight = null,
    ): ExchangeItemMetric {
        $item = ExchangeItem::query()
            ->where('poe_league_id', $league->id)
            ->where('trade_id', $tradeId)
            ->first() ?? $this->item($league, $tradeId);

        return ExchangeItemMetric::factory()
            ->forItem($item, $metricsItem)
            ->atBucket($bucketStart)
            ->state([
                'log_return' => $logReturn,
                'residual' => $residual,
                'residual_mad' => $residualMad,
                'observations' => $observations,
                'units' => $units,
                'return_weight' => $returnWeight,
            ])
            ->create();
    }
}
