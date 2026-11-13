<?php

namespace Tests\Feature;

use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ItemCategoryTagEnum;
use App\Enums\Tags\MetricsItemTagEnum;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\PoeLeague;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;
use UnitEnum;

class LeagueItemDetailRouteFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private const string LAST_BUCKET = '2030-01-10 12:00:00';

    /**
     * @return array<string, array{string}>
     */
    public static function endpoints(): array
    {
        return [
            'summary' => ['summary'],
            'history' => ['history'],
            'buckets' => ['buckets'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_unknown_league_is_not_found(string $endpoint): void
    {
        $this->getJson("/api/leagues/no-such-league/items/exalted/{$endpoint}")->assertNotFound();
    }

    #[DataProvider('endpoints')]
    public function test_inactive_league_is_not_found(string $endpoint): void
    {
        [$league] = $this->leagueWithBucket('Detail Inactive League');
        $this->categorizedItem($league, 'exalted', 'Currency');
        $league->update(['is_active' => false]);

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/{$endpoint}")->assertNotFound();
    }

    #[DataProvider('endpoints')]
    public function test_unknown_item_is_not_found(string $endpoint): void
    {
        [$league] = $this->leagueWithBucket('Detail Unknown Item League');

        $this->getJson("/api/leagues/{$league->slug}/items/no-such-item/{$endpoint}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Item not found']);
    }

    #[DataProvider('endpoints')]
    public function test_item_of_another_league_is_not_found(string $endpoint): void
    {
        [$league] = $this->leagueWithBucket('Detail Own League');
        [$other] = $this->leagueWithBucket('Detail Other League');
        $this->categorizedItem($other, 'mirror', 'Currency');

        $this->getJson("/api/leagues/{$league->slug}/items/mirror/{$endpoint}")->assertNotFound();
    }

    #[DataProvider('endpoints')]
    public function test_unknown_currency_is_rejected(string $endpoint): void
    {
        [$league] = $this->leagueWithBucket('Detail Currency League');
        $this->categorizedItem($league, 'exalted', 'Currency');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/{$endpoint}?currency=no-such")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_the_categories_route_is_not_taken_by_the_item_routes(): void
    {
        [$league] = $this->leagueWithBucket('Detail Categories Route League');
        $this->categorizedItem($league, 'categories', 'Currency');

        $this->getJson("/api/leagues/{$league->slug}/items/categories")
            ->assertOk()
            ->assertJsonStructure(['categories', 'hasUncategorized']);
    }

    public function test_summary_has_the_documented_shape_and_numeric_values(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Summary Shape League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency', 'Exalted Orb');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, [
            'price' => 2.5,
            'log_return' => 0.0121,
            'residual' => 0.0032,
            'residual_mad' => 0.0049,
            'observations' => 14,
            'units' => 312.5,
            'return_weight' => 0.8,
        ]);
        $this->metric($metricsItem, $item, '2030-01-10 00:00:00', ['price' => 2.4]);
        $this->beta($metricsItem, $item, '2030-01-10 00:00:00', 0.98, 0.9, 0.42, 12);

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")
            ->assertOk()
            ->assertExactJson([
                'item' => ['slug' => 'exalted', 'name' => 'Exalted Orb', 'category' => 'Currency'],
                'currency' => 'divine',
                'lastBucket' => '2030-01-10T18:00:00+00:00',
                'currentPrice' => null,
                'currentPriceAt' => null,
                'current' => [
                    'price' => 2.5,
                    'logReturn' => 0.0121,
                    'residual' => 0.0032,
                    'residualMad' => 0.0049,
                    'observations' => 14,
                    'units' => 312.5,
                    'returnWeight' => 0.8,
                    'lowData' => false,
                    'isUnusual' => false,
                ],
                'beta' => [
                    'calculatedAt' => '2030-01-10T00:00:00+00:00',
                    'beta' => 0.98,
                    'rawBeta' => 0.9,
                    'rSquared' => 0.42,
                    'observations' => 12,
                ],
            ]);
    }

    public function test_summary_carries_the_price_from_the_latest_snapshot(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Fresh Price League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $this->snapshot($league, $this->exchangeType(), '2030-01-10 14:00:00', ['exalted' => 7.5]);

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")
            ->assertOk()
            ->assertJsonPath('currentPrice', 7.5)
            ->assertJsonPath('currentPriceAt', '2030-01-10T14:00:00+00:00')
            ->assertJsonPath('current.price', 2.5);
    }

    public function test_summary_of_an_item_without_metrics_has_null_current_and_beta(): void
    {
        [$league] = $this->leagueWithBucket('Detail Summary Null League');
        $this->categorizedItem($league, 'fragment', null);

        $json = $this->getJson("/api/leagues/{$league->slug}/items/fragment/summary")->assertOk()->json();

        $this->assertSame(['slug' => 'fragment', 'name' => 'FRAGMENT', 'category' => null], $json['item']);
        $this->assertSame('divine', $json['currency']);
        $this->assertSame('2030-01-10T18:00:00+00:00', $json['lastBucket']);
        $this->assertNull($json['current']);
        $this->assertNull($json['beta']);
    }

    public function test_summary_without_metrics_in_the_last_bucket_ignores_older_buckets(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Summary Old Bucket League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, '2030-01-10 00:00:00', ['price' => 2.4]);

        $this->assertNull($this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")->assertOk()->json('current'));
    }

    public function test_an_empty_category_is_reported_as_null(): void
    {
        [$league] = $this->leagueWithBucket('Detail Empty Category League');
        $this->categorizedItem($league, 'scarab', '');

        $this->getJson("/api/leagues/{$league->slug}/items/scarab/summary")
            ->assertOk()
            ->assertJsonPath('item.category', null);
    }

    public function test_summary_of_a_league_without_metrics_items_has_a_null_currency(): void
    {
        $league = $this->league('Detail No Metrics League');
        $this->item($league, 'exalted');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")
            ->assertOk()
            ->assertExactJson([
                'item' => ['slug' => 'exalted', 'name' => 'EXALTED', 'category' => null],
                'currency' => null,
                'lastBucket' => null,
                'currentPrice' => null,
                'currentPriceAt' => null,
                'current' => null,
                'beta' => null,
            ]);
    }

    public function test_summary_of_a_league_without_metrics_items_still_reports_unknown_items(): void
    {
        $league = $this->league('Detail No Metrics Unknown League');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")->assertNotFound();
    }

    public function test_summary_of_a_league_without_buckets_has_the_currency_only(): void
    {
        $league = $this->league('Detail No Buckets League');
        $this->metricsItem($league, $this->item($league, 'divine'));
        $this->item($league, 'exalted');

        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")->assertOk()->json();

        $this->assertSame('divine', $json['currency']);
        $this->assertNull($json['lastBucket']);
        $this->assertNull($json['current']);
    }

    public function test_the_latest_beta_is_reported(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Latest Beta League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->beta($metricsItem, $item, '2030-01-08 00:00:00', 0.5);
        $this->beta($metricsItem, $item, '2030-01-10 00:00:00', 0.7);
        $this->beta($metricsItem, $item, '2030-01-09 00:00:00', 0.6);

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")
            ->assertOk()
            ->assertJsonPath('beta.beta', 0.7)
            ->assertJsonPath('beta.calculatedAt', '2030-01-10T00:00:00+00:00');
    }

    /**
     * @return array<string, array{int, float|null, float|null, bool, bool}>
     */
    public static function currentFlags(): array
    {
        return [
            'beyond twice the mad' => [14, 0.011, 0.005, false, true],
            'exactly twice the mad' => [14, 0.01, 0.005, false, false],
            'negative beyond twice the mad' => [14, -0.011, 0.005, false, true],
            'zero mad' => [14, 0.5, 0.0, false, false],
            'no residual' => [14, null, 0.005, false, false],
            'no mad' => [14, 0.5, null, false, false],
            'few observations' => [5, 0.001, 0.005, true, false],
            'threshold observations' => [6, 0.001, 0.005, false, false],
        ];
    }

    #[DataProvider('currentFlags')]
    public function test_summary_flags_low_data_and_unusual_moves(
        int $observations,
        ?float $residual,
        ?float $mad,
        bool $lowData,
        bool $isUnusual,
    ): void {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Flags League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, [
            'observations' => $observations,
            'residual' => $residual,
            'residual_mad' => $mad,
        ]);

        $current = $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")->assertOk()->json('current');

        $this->assertSame($lowData, $current['lowData']);
        $this->assertSame($isUnusual, $current['isUnusual']);
    }

    public function test_summary_is_measured_in_the_requested_currency(): void
    {
        [$league, $divine] = $this->leagueWithBucket('Detail Summary Currency League');
        $mirror = $this->categorizedItem($league, 'mirror', 'Currency');
        $mirrorMetrics = $this->metricsItem($league, $mirror);
        ExchangeMarketMetric::factory()->forMetricsItem($mirrorMetrics)->atBucket('2030-01-10 00:00:00')->create();
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($divine, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $this->metric($mirrorMetrics, $item, '2030-01-10 00:00:00', ['price' => 0.001]);

        $default = $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary")->assertOk();
        $mirrorResponse = $this->getJson("/api/leagues/{$league->slug}/items/exalted/summary?currency=mirror")->assertOk();

        $this->assertSame('divine', $default->json('currency'));
        $this->assertSame(2.5, $default->json('current.price'));
        $this->assertSame('mirror', $mirrorResponse->json('currency'));
        $this->assertSame(0.001, $mirrorResponse->json('current.price'));
        $this->assertSame('2030-01-10T06:00:00+00:00', $mirrorResponse->json('lastBucket'));
    }

    public function test_summary_is_cached_until_a_league_tag_is_flushed(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Summary Cached League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $metric = $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $url = "/api/leagues/{$league->slug}/items/exalted/summary";

        $this->assertSame(2.5, $this->getJson($url)->json('current.price'));

        $metric->update(['price' => 3.5]);

        $this->assertSame(2.5, $this->getJson($url)->json('current.price'));

        Cache::tags([BucketMetricsTagEnum::bucket_metrics_league_id->name.$league->id])->flush();

        $this->assertSame(3.5, $this->getJson($url)->json('current.price'));
    }

    /**
     * @return array<string, array{UnitEnum}>
     */
    public static function summaryTags(): array
    {
        return [
            'bucket metrics' => [BucketMetricsTagEnum::bucket_metrics_league_id],
            'betas' => [BetasMetricsTagEnum::betas_metrics_league_id],
            'metrics items' => [MetricsItemTagEnum::metrics_items_league_id],
            'item categories' => [ItemCategoryTagEnum::item_categories_league_id],
        ];
    }

    #[DataProvider('summaryTags')]
    public function test_each_league_tag_invalidates_the_summary(UnitEnum $tag): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Summary Tags League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET);
        $url = "/api/leagues/{$league->slug}/items/exalted/summary";

        $this->getJson($url)->assertOk();
        $item->update(['name' => 'Renamed']);

        $this->assertSame('EXALTED', $this->getJson($url)->json('item.name'));

        Cache::tags([$tag->name.$league->id])->flush();

        $this->assertSame('Renamed', $this->getJson($url)->json('item.name'));
    }

    public function test_flushing_another_league_does_not_invalidate_the_summary(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Summary Isolated League');
        [$other] = $this->leagueWithBucket('Detail Summary Other League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $metric = $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $url = "/api/leagues/{$league->slug}/items/exalted/summary";

        $this->getJson($url)->assertOk();
        $metric->update(['price' => 3.5]);
        Cache::tags([BucketMetricsTagEnum::bucket_metrics_league_id->name.$other->id])->flush();

        $this->assertSame(2.5, $this->getJson($url)->json('current.price'));
    }

    public function test_a_missing_item_is_not_cached_as_not_found(): void
    {
        [$league] = $this->leagueWithBucket('Detail Summary Late Item League');
        $url = "/api/leagues/{$league->slug}/items/exalted/summary";

        $this->getJson($url)->assertNotFound();

        $this->categorizedItem($league, 'exalted', 'Currency');

        $this->getJson($url)->assertOk();
    }

    public function test_history_has_the_documented_shape_and_numeric_values(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail History Shape League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5, 'residual' => 0.003, 'residual_mad' => 0.005]);
        $this->metric($metricsItem, $item, '2030-01-10 00:00:00', ['price' => 2.4]);
        $this->beta($metricsItem, $item, '2030-01-10 00:00:00', 0.98, 0.9, null, 12);

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/history")
            ->assertOk()
            ->assertExactJson([
                'currency' => 'divine',
                'period' => '7d',
                'lastBucket' => '2030-01-10T12:00:00+00:00',
                'series' => [
                    ['bucket' => '2030-01-10T06:00:00+00:00', 'price' => 2.4, 'residual' => null, 'residualMad' => null],
                    ['bucket' => '2030-01-10T18:00:00+00:00', 'price' => 2.5, 'residual' => 0.003, 'residualMad' => 0.005],
                ],
                'betaHistory' => [
                    [
                        'calculatedAt' => '2030-01-10T00:00:00+00:00',
                        'beta' => 0.98,
                        'rawBeta' => 0.9,
                        'rSquared' => null,
                        'observations' => 12,
                    ],
                ],
            ]);
    }

    /**
     * @return array<string, array{string|null, string, array<int, string>, array<int, string>}>
     */
    public static function historyWindows(): array
    {
        return [
            'default is a week' => [
                null,
                '7d',
                ['2030-01-09 18:00:00', '2030-01-10 06:00:00', '2030-01-10 18:00:00'],
                ['2030-01-08 00:00:00', '2030-01-10 00:00:00'],
            ],
            'day is the last two buckets' => [
                '24h',
                '24h',
                ['2030-01-10 06:00:00', '2030-01-10 18:00:00'],
                ['2030-01-10 00:00:00'],
            ],
            'week' => [
                '7d',
                '7d',
                ['2030-01-09 18:00:00', '2030-01-10 06:00:00', '2030-01-10 18:00:00'],
                ['2030-01-08 00:00:00', '2030-01-10 00:00:00'],
            ],
            'league is everything' => [
                'league',
                'league',
                ['2030-01-02 06:00:00', '2030-01-03 18:00:00', '2030-01-09 18:00:00', '2030-01-10 06:00:00', '2030-01-10 18:00:00'],
                ['2030-01-02 00:00:00', '2030-01-08 00:00:00', '2030-01-10 00:00:00'],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $expectedBuckets
     * @param  array<int, string>  $expectedBetas
     */
    #[DataProvider('historyWindows')]
    public function test_history_is_limited_to_the_period(
        ?string $period,
        string $expectedPeriod,
        array $expectedBuckets,
        array $expectedBetas,
    ): void {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail History Window League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');

        foreach (['2030-01-10 12:00:00', '2030-01-10 00:00:00', '2030-01-09 12:00:00', '2030-01-03 12:00:00', '2030-01-02 00:00:00'] as $bucket) {
            $this->metric($metricsItem, $item, $bucket);
        }

        foreach (['2030-01-10 00:00:00', '2030-01-08 00:00:00', '2030-01-02 00:00:00'] as $calculatedAt) {
            $this->beta($metricsItem, $item, $calculatedAt, 1.0);
        }

        $query = $period === null ? '' : "?period={$period}";
        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/history{$query}")->assertOk()->json();

        $this->assertSame($expectedPeriod, $json['period']);
        $this->assertSame(
            array_map(static fn (string $moment): string => str_replace(' ', 'T', $moment).'+00:00', $expectedBuckets),
            array_column($json['series'], 'bucket'),
        );
        $this->assertSame(
            array_map(static fn (string $moment): string => str_replace(' ', 'T', $moment).'+00:00', $expectedBetas),
            array_column($json['betaHistory'], 'calculatedAt'),
        );
    }

    public function test_history_only_has_the_requested_item_and_currency(): void
    {
        [$league, $divine] = $this->leagueWithBucket('Detail History Scope League');
        $mirror = $this->categorizedItem($league, 'mirror', 'Currency');
        $mirrorMetrics = $this->metricsItem($league, $mirror);
        ExchangeMarketMetric::factory()->forMetricsItem($mirrorMetrics)->atBucket(self::LAST_BUCKET)->create();
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $other = $this->categorizedItem($league, 'chaos', 'Currency');
        $this->metric($divine, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $this->metric($divine, $other, self::LAST_BUCKET, ['price' => 9.9]);
        $this->metric($mirrorMetrics, $item, self::LAST_BUCKET, ['price' => 0.001]);
        $this->beta($divine, $other, '2030-01-10 00:00:00', 5.0);
        $this->beta($mirrorMetrics, $item, '2030-01-10 00:00:00', 6.0);

        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/history")->assertOk()->json();

        $this->assertSame([2.5], array_column($json['series'], 'price'));
        $this->assertSame([], $json['betaHistory']);
    }

    public function test_history_of_an_item_without_metrics_is_empty(): void
    {
        [$league] = $this->leagueWithBucket('Detail History Empty League');
        $this->categorizedItem($league, 'fragment', null);

        $this->getJson("/api/leagues/{$league->slug}/items/fragment/history")
            ->assertOk()
            ->assertJsonPath('series', [])
            ->assertJsonPath('betaHistory', [])
            ->assertJsonPath('lastBucket', '2030-01-10T12:00:00+00:00');
    }

    public function test_history_of_a_league_without_metrics_items_is_empty(): void
    {
        $league = $this->league('Detail History No Metrics League');
        $this->item($league, 'exalted');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/history?period=league")
            ->assertOk()
            ->assertExactJson([
                'currency' => null,
                'period' => 'league',
                'lastBucket' => null,
                'series' => [],
                'betaHistory' => [],
            ]);
    }

    public function test_history_of_a_league_without_buckets_has_the_currency_only(): void
    {
        $league = $this->league('Detail History No Buckets League');
        $this->metricsItem($league, $this->item($league, 'divine'));
        $this->item($league, 'exalted');

        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/history")->assertOk()->json();

        $this->assertSame('divine', $json['currency']);
        $this->assertNull($json['lastBucket']);
        $this->assertSame([], $json['series']);
    }

    public function test_the_history_response_is_not_cached(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail History Not Cached League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $metric = $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $url = "/api/leagues/{$league->slug}/items/exalted/history";

        $this->assertSame(2.5, $this->getJson($url)->json('series.0.price'));

        $metric->update(['price' => 3.5]);

        $this->assertSame(3.5, $this->getJson($url)->json('series.0.price'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPeriods(): array
    {
        return [
            'unknown period' => ['period=month'],
            'uppercase period' => ['period=LEAGUE'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_an_invalid_period_is_rejected(string $query): void
    {
        [$league] = $this->leagueWithBucket('Detail Invalid Period League');
        $this->categorizedItem($league, 'exalted', 'Currency');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/history?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
        $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_buckets_have_the_documented_shape_and_numeric_values_newest_first(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Buckets Shape League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, '2030-01-10 00:00:00', ['price' => 2.4]);
        $this->metric($metricsItem, $item, self::LAST_BUCKET, [
            'price' => 2.5,
            'log_return' => 0.0121,
            'residual' => 0.0032,
            'residual_mad' => 0.0049,
            'observations' => 14,
            'units' => 312.5,
            'return_weight' => 0.8,
        ]);

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets")
            ->assertOk()
            ->assertExactJson([
                'currency' => 'divine',
                'period' => '7d',
                'data' => [
                    [
                        'bucket' => '2030-01-10T18:00:00+00:00',
                        'price' => 2.5,
                        'logReturn' => 0.0121,
                        'residual' => 0.0032,
                        'residualMad' => 0.0049,
                        'observations' => 14,
                        'units' => 312.5,
                        'returnWeight' => 0.8,
                    ],
                    [
                        'bucket' => '2030-01-10T06:00:00+00:00',
                        'price' => 2.4,
                        'logReturn' => null,
                        'residual' => null,
                        'residualMad' => null,
                        'observations' => 0,
                        'units' => null,
                        'returnWeight' => null,
                    ],
                ],
                'meta' => [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'perPage' => 28,
                    'total' => 2,
                ],
            ]);
    }

    public function test_buckets_are_paginated(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Buckets Pages League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');

        foreach (['2030-01-10 12:00:00', '2030-01-10 00:00:00', '2030-01-09 12:00:00', '2030-01-09 00:00:00', '2030-01-08 12:00:00'] as $bucket) {
            $this->metric($metricsItem, $item, $bucket);
        }

        $first = $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets?perPage=2")->assertOk()->json();
        $last = $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets?perPage=2&page=3")->assertOk()->json();
        $beyond = $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets?perPage=2&page=4")->assertOk()->json();

        $this->assertSame(
            ['currentPage' => 1, 'lastPage' => 3, 'perPage' => 2, 'total' => 5],
            $first['meta'],
        );
        $this->assertSame(
            ['2030-01-10T18:00:00+00:00', '2030-01-10T06:00:00+00:00'],
            array_column($first['data'], 'bucket'),
        );
        $this->assertSame(['2030-01-08T18:00:00+00:00'], array_column($last['data'], 'bucket'));
        $this->assertSame([], $beyond['data']);
        $this->assertSame(5, $beyond['meta']['total']);
    }

    public function test_buckets_are_limited_to_the_period(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Buckets Window League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');

        foreach (['2030-01-10 12:00:00', '2030-01-10 00:00:00', '2030-01-09 12:00:00', '2030-01-03 12:00:00', '2030-01-02 00:00:00'] as $bucket) {
            $this->metric($metricsItem, $item, $bucket);
        }

        $base = "/api/leagues/{$league->slug}/items/exalted/buckets";

        $this->assertSame(2, $this->getJson("{$base}?period=24h")->json('meta.total'));
        $this->assertSame(3, $this->getJson("{$base}?period=7d")->json('meta.total'));
        $this->assertSame(5, $this->getJson("{$base}?period=league")->json('meta.total'));
        $this->assertSame('league', $this->getJson("{$base}?period=league")->json('period'));
    }

    public function test_buckets_only_have_the_requested_item(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Buckets Scope League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $other = $this->categorizedItem($league, 'chaos', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $this->metric($metricsItem, $other, self::LAST_BUCKET, ['price' => 9.9]);

        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets")->assertOk()->json();

        $this->assertSame([2.5], array_column($json['data'], 'price'));
        $this->assertSame(1, $json['meta']['total']);
    }

    public function test_buckets_of_an_item_without_metrics_are_empty(): void
    {
        [$league] = $this->leagueWithBucket('Detail Buckets Empty League');
        $this->categorizedItem($league, 'fragment', null);

        $this->getJson("/api/leagues/{$league->slug}/items/fragment/buckets")
            ->assertOk()
            ->assertExactJson([
                'currency' => 'divine',
                'period' => '7d',
                'data' => [],
                'meta' => [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'perPage' => 28,
                    'total' => 0,
                ],
            ]);
    }

    public function test_buckets_of_a_league_without_metrics_items_are_empty_without_a_currency(): void
    {
        $league = $this->league('Detail Buckets No Metrics League');
        $this->item($league, 'exalted');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets")
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('currency', null)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.lastPage', 1);
    }

    public function test_buckets_of_a_league_without_buckets_are_empty_with_the_currency(): void
    {
        $league = $this->league('Detail Buckets No Buckets League');
        $this->metricsItem($league, $this->item($league, 'divine'));
        $this->item($league, 'exalted');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets")
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('currency', 'divine');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPages(): array
    {
        return [
            'zero page' => ['page=0'],
            'text page' => ['page=abc'],
            'zero per page' => ['perPage=0'],
            'too many per page' => ['perPage=101'],
        ];
    }

    #[DataProvider('invalidPages')]
    public function test_invalid_pagination_is_rejected(string $query): void
    {
        [$league] = $this->leagueWithBucket('Detail Invalid Page League');
        $this->categorizedItem($league, 'exalted', 'Currency');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/buckets?{$query}")->assertUnprocessable();
    }

    public function test_the_buckets_response_is_not_cached(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Detail Buckets Not Cached League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $metric = $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $url = "/api/leagues/{$league->slug}/items/exalted/buckets";

        $this->assertSame(2.5, $this->getJson($url)->json('data.0.price'));

        $metric->update(['price' => 3.5]);

        $this->assertSame(3.5, $this->getJson($url)->json('data.0.price'));
    }

    /**
     * @return array{0: PoeLeague, 1: ExchangeMetricsItem}
     */
    private function leagueWithBucket(string $name): array
    {
        $league = $this->league($name);
        $metricsItem = $this->metricsItem($league, $this->item($league, 'divine'));
        ExchangeMarketMetric::factory()->forMetricsItem($metricsItem)->atBucket(self::LAST_BUCKET)->create();

        return [$league, $metricsItem];
    }

    private function categorizedItem(PoeLeague $league, string $tradeId, ?string $category, ?string $name = null): ExchangeItem
    {
        return ExchangeItem::query()->create([
            'name' => $name ?? strtoupper($tradeId),
            'trade_id' => $tradeId,
            'poe_category' => $category,
            'poe_league_id' => $league->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function metric(ExchangeMetricsItem $metricsItem, ExchangeItem $item, string $bucketStart, array $state = []): ExchangeItemMetric
    {
        return ExchangeItemMetric::factory()
            ->forItem($item, $metricsItem)
            ->atBucket($bucketStart)
            ->state($state)
            ->create();
    }

    private function beta(
        ExchangeMetricsItem $metricsItem,
        ExchangeItem $item,
        string $calculatedAt,
        float $beta,
        ?float $rawBeta = null,
        ?float $rSquared = 0.5,
        int $observations = 14,
    ): ExchangeItemBeta {
        return ExchangeItemBeta::factory()
            ->forItem($item, $metricsItem)
            ->calculatedAt($calculatedAt)
            ->state([
                'beta' => $beta,
                'raw_beta' => $rawBeta ?? $beta,
                'r_squared' => $rSquared,
                'observations' => $observations,
            ])
            ->create();
    }
}
