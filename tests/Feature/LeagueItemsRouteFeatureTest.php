<?php

namespace Tests\Feature;

use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ItemCategoryTagEnum;
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

class LeagueItemsRouteFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private const string LAST_BUCKET = '2030-01-10 12:00:00';

    public function test_unknown_league_is_not_found(): void
    {
        $this->getJson('/api/leagues/no-such-league/items')->assertNotFound();
        $this->getJson('/api/leagues/no-such-league/items/categories')->assertNotFound();
    }

    public function test_inactive_league_is_not_found(): void
    {
        $league = $this->league('Items Inactive League');
        $league->update(['is_active' => false]);

        $this->getJson("/api/leagues/{$league->slug}/items")->assertNotFound();
        $this->getJson("/api/leagues/{$league->slug}/items/categories")->assertNotFound();
    }

    public function test_league_without_metrics_items_has_an_empty_page(): void
    {
        $league = $this->league('Items No Metrics League');
        $this->item($league, 'divine');

        $this->getJson("/api/leagues/{$league->slug}/items")
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'perPage' => 50,
                    'total' => 0,
                    'currency' => null,
                    'lastBucket' => null,
                    'lastSnapshotAt' => null,
                ],
            ]);
    }

    public function test_league_without_buckets_has_an_empty_page_with_the_currency(): void
    {
        $league = $this->league('Items No Buckets League');
        $this->metricsItem($league, $this->item($league, 'divine'));

        $json = $this->getJson("/api/leagues/{$league->slug}/items")->assertOk()->json();

        $this->assertSame([], $json['data']);
        $this->assertSame('divine', $json['meta']['currency']);
        $this->assertNull($json['meta']['lastBucket']);
        $this->assertSame(0, $json['meta']['total']);
    }

    public function test_items_have_the_documented_shape_and_numeric_values(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Shape League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, [
            'price' => 2.5,
            'log_return' => 0.0121,
            'residual' => 0.0032,
            'residual_mad' => 0.0049,
            'observations' => 14,
            'units' => 312.5,
            'return_weight' => 0.8,
        ]);
        $this->metric($metricsItem, $item, '2030-01-09 00:00:00', ['price' => 2.4]);
        $this->beta($metricsItem, $item, '2030-01-10 00:00:00', 0.98);

        $json = $this->getJson("/api/leagues/{$league->slug}/items?category=Currency")->assertOk()->json();

        $this->assertSame(['data', 'meta'], array_keys($json));
        $this->assertSame(
            ['currentPage' => 1, 'lastPage' => 1, 'perPage' => 50, 'total' => 1, 'currency' => 'divine', 'lastBucket' => '2030-01-10T12:00:00+00:00', 'lastSnapshotAt' => null],
            $json['meta'],
        );
        $this->assertSame(
            [
                'slug' => 'exalted',
                'name' => 'EXALTED',
                'category' => 'Currency',
                'price' => 2.5,
                'logReturn' => 0.0121,
                'residual' => 0.0032,
                'residualMad' => 0.0049,
                'beta' => 0.98,
                'observations' => 14,
                'units' => 312.5,
                'returnWeight' => 0.8,
                'lowData' => false,
                'isUnusual' => false,
                'sparkline' => [
                    ['bucket' => '2030-01-09T00:00:00+00:00', 'value' => 2.4],
                    ['bucket' => '2030-01-10T12:00:00+00:00', 'value' => 2.5],
                ],
            ],
            $json['data'][0],
        );
    }

    public function test_the_price_comes_from_the_latest_snapshot_and_is_marked_with_its_moment(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Fresh Price League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $this->snapshot($league, $this->exchangeType(), '2030-01-10 14:00:00', ['exalted' => 7.5]);

        $json = $this->getJson("/api/leagues/{$league->slug}/items?category=Currency")->assertOk()->json();

        $this->assertSame(7.5, $json['data'][0]['price']);
        $this->assertSame('2030-01-10T14:00:00+00:00', $json['meta']['lastSnapshotAt']);
    }

    public function test_an_item_missing_from_the_latest_snapshot_keeps_the_bucket_price(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Fresh Fallback League');
        $item = $this->categorizedItem($league, 'exalted', 'Currency');
        $this->categorizedItem($league, 'mirror', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $this->snapshot($league, $this->exchangeType(), '2030-01-10 14:00:00', ['mirror' => 90.5]);

        $row = $this->getJson("/api/leagues/{$league->slug}/items?category=Currency")->assertOk()->json('data.0');

        $this->assertSame('exalted', $row['slug']);
        $this->assertSame(2.5, $row['price']);
    }

    public function test_items_without_metrics_in_the_last_bucket_are_kept_with_null_metrics(): void
    {
        [$league] = $this->leagueWithBucket('Items Null Metrics League');
        $this->categorizedItem($league, 'fragment', 'Fragments');

        $row = $this->getJson("/api/leagues/{$league->slug}/items?category=Fragments")->assertOk()->json('data.0');

        $this->assertSame('fragment', $row['slug']);
        $this->assertNull($row['price']);
        $this->assertNull($row['logReturn']);
        $this->assertNull($row['residual']);
        $this->assertNull($row['residualMad']);
        $this->assertNull($row['beta']);
        $this->assertNull($row['units']);
        $this->assertNull($row['returnWeight']);
        $this->assertSame(0, $row['observations']);
        $this->assertTrue($row['lowData']);
        $this->assertFalse($row['isUnusual']);
        $this->assertSame([], $row['sparkline']);
    }

    public function test_the_sparkline_is_limited_to_the_configured_days(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Sparkline League');
        $item = $this->categorizedItem($league, 'scarab', 'Scarabs');
        $this->metric($metricsItem, $item, '2030-01-02 12:00:00', ['price' => 1.5]);
        $this->metric($metricsItem, $item, '2030-01-03 00:00:00', ['price' => 2.5]);
        $this->metric($metricsItem, $item, '2030-01-04 00:00:00', ['price' => 3.5]);
        $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 4.5]);

        $sparkline = $this->getJson("/api/leagues/{$league->slug}/items?category=Scarabs")->json('data.0.sparkline');

        $this->assertSame([3.5, 4.5], array_column($sparkline, 'value'));
    }

    public function test_the_latest_beta_of_the_item_is_used(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Beta League');
        $item = $this->categorizedItem($league, 'beta-item', 'Beta');
        $this->beta($metricsItem, $item, '2030-01-01 00:00:00', 0.5);
        $this->beta($metricsItem, $item, '2030-01-08 00:00:00', 1.25);

        $this->assertSame(1.25, $this->getJson("/api/leagues/{$league->slug}/items?category=Beta")->json('data.0.beta'));
    }

    /**
     * @return array<string, array{0: int, 1: float|null, 2: float|null, 3: bool}>
     */
    public static function unusualResiduals(): array
    {
        return [
            'above twice the mad' => [10, 0.1, 0.03, true],
            'below twice the mad' => [10, 0.05, 0.03, false],
            'negative above twice the mad' => [10, -0.1, 0.03, true],
            'zero mad' => [10, 0.1, 0.0, false],
            'no residual' => [10, null, 0.03, false],
            'no mad' => [10, 0.1, null, false],
        ];
    }

    #[DataProvider('unusualResiduals')]
    public function test_unusual_items_are_flagged(int $observations, ?float $residual, ?float $mad, bool $expected): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Unusual League');
        $item = $this->categorizedItem($league, 'moving', 'Moving');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, [
            'observations' => $observations,
            'residual' => $residual,
            'residual_mad' => $mad,
        ]);

        $this->assertSame($expected, $this->getJson("/api/leagues/{$league->slug}/items?category=Moving")->json('data.0.isUnusual'));
    }

    public function test_items_with_few_observations_are_marked_as_low_data(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Low Data League');
        $this->metric($metricsItem, $this->categorizedItem($league, 'few', 'Data'), self::LAST_BUCKET, ['observations' => 5]);
        $this->metric($metricsItem, $this->categorizedItem($league, 'enough', 'Data'), self::LAST_BUCKET, ['observations' => 6]);

        $rows = $this->getJson("/api/leagues/{$league->slug}/items?category=Data&sort=name")->json('data');

        $this->assertSame(['enough' => false, 'few' => true], array_column($rows, 'lowData', 'slug'));
    }

    public function test_without_a_category_filter_every_item_is_listed(): void
    {
        [$league] = $this->leagueWithBucket('Items All Categories League');
        $this->categorizedItem($league, 'with-category', 'Currency');
        $this->categorizedItem($league, 'empty-category', '');
        $this->categorizedItem($league, 'null-category', null);

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?sort=name")->json('data'));

        $this->assertSame(['divine', 'empty-category', 'null-category', 'with-category'], $slugs);
    }

    public function test_items_are_filtered_by_category(): void
    {
        [$league] = $this->leagueWithBucket('Items Category League');
        $this->categorizedItem($league, 'currency-one', 'Currency');
        $this->categorizedItem($league, 'scarab-one', 'Scarabs');
        $this->categorizedItem($league, 'no-category', null);

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?category=Currency")->json('data'));

        $this->assertSame(['currency-one'], $slugs);
    }

    public function test_uncategorized_items_have_no_or_an_empty_category(): void
    {
        [$league] = $this->leagueWithBucket('Items Uncategorized League');
        $this->categorizedItem($league, 'currency-one', 'Currency');
        $this->categorizedItem($league, 'empty-category', '');
        $this->categorizedItem($league, 'null-category', null);

        $rows = $this->getJson("/api/leagues/{$league->slug}/items?uncategorized=1&sort=name")->json('data');

        $this->assertSame(['divine', 'empty-category', 'null-category'], $this->slugs($rows));
        $this->assertSame([null, null, null], array_column($rows, 'category'));
    }

    public function test_category_and_uncategorized_cannot_be_combined(): void
    {
        [$league] = $this->leagueWithBucket('Items Both Filters League');

        $this->getJson("/api/leagues/{$league->slug}/items?category=Currency&uncategorized=1")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_search_ignores_case_and_matches_a_substring(): void
    {
        [$league] = $this->leagueWithBucket('Items Search League');
        $this->categorizedItem($league, 'one', 'Currency', 'Orb of Alchemy');
        $this->categorizedItem($league, 'two', 'Currency', 'Chaos Orb');
        $this->categorizedItem($league, 'three', 'Currency', 'Scarab');

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?search=ORB&sort=name")->json('data'));

        $this->assertSame(['two', 'one'], $slugs);
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        [$league] = $this->leagueWithBucket('Items Wildcard League');
        $this->categorizedItem($league, 'percent', 'Currency', '100% Orb');
        $this->categorizedItem($league, 'plain', 'Currency', '1000 Orb');
        $this->categorizedItem($league, 'underscore', 'Currency', 'a_b');
        $this->categorizedItem($league, 'letter', 'Currency', 'axb');

        $this->assertSame(['percent'], $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?search=".urlencode('100%'))->json('data')));
        $this->assertSame(['underscore'], $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?search=a_b")->json('data')));
    }

    public function test_search_and_category_filters_combine(): void
    {
        [$league] = $this->leagueWithBucket('Items Combined Filters League');
        $this->categorizedItem($league, 'currency-orb', 'Currency', 'Chaos Orb');
        $this->categorizedItem($league, 'scarab-orb', 'Scarabs', 'Orb Scarab');

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?search=orb&category=Scarabs")->json('data'));

        $this->assertSame(['scarab-orb'], $slugs);
    }

    public function test_items_are_sorted_by_units_descending_by_default_with_missing_values_last(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Default Sort League');
        $this->metric($metricsItem, $this->categorizedItem($league, 'slow', 'Sort'), self::LAST_BUCKET, ['units' => 5.0]);
        $this->metric($metricsItem, $this->categorizedItem($league, 'fast', 'Sort'), self::LAST_BUCKET, ['units' => 50.0]);
        $this->categorizedItem($league, 'no-metrics', 'Sort');

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?category=Sort")->json('data'));

        $this->assertSame(['fast', 'slow', 'no-metrics'], $slugs);
    }

    public function test_missing_values_go_last_in_both_directions(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Nulls Last League');
        $this->metric($metricsItem, $this->categorizedItem($league, 'cheap', 'Sort'), self::LAST_BUCKET, ['price' => 1.0]);
        $this->metric($metricsItem, $this->categorizedItem($league, 'dear', 'Sort'), self::LAST_BUCKET, ['price' => 9.0]);
        $this->categorizedItem($league, 'no-metrics', 'Sort');

        $ascending = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?category=Sort&sort=price")->json('data'));
        $descending = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?category=Sort&sort=-price")->json('data'));

        $this->assertSame(['cheap', 'dear', 'no-metrics'], $ascending);
        $this->assertSame(['dear', 'cheap', 'no-metrics'], $descending);
    }

    public function test_items_are_sorted_by_the_unusual_ratio(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Ratio League');
        $this->metric($metricsItem, $this->categorizedItem($league, 'mild', 'Sort'), self::LAST_BUCKET, ['residual' => 0.04, 'residual_mad' => 0.02]);
        $this->metric($metricsItem, $this->categorizedItem($league, 'wild', 'Sort'), self::LAST_BUCKET, ['residual' => -0.5, 'residual_mad' => 0.05]);
        $this->metric($metricsItem, $this->categorizedItem($league, 'zero-mad', 'Sort'), self::LAST_BUCKET, ['residual' => 0.5, 'residual_mad' => 0.0]);

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?category=Sort&sort=-unusualRatio")->json('data'));

        $this->assertSame(['wild', 'mild', 'zero-mad'], $slugs);
    }

    public function test_equal_values_are_ordered_by_the_item_id(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Ties League');

        foreach (['first', 'second', 'third'] as $tradeId) {
            $this->metric($metricsItem, $this->categorizedItem($league, $tradeId, 'Ties'), self::LAST_BUCKET, ['units' => 7.0]);
        }

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items?category=Ties")->json('data'));

        $this->assertSame(['first', 'second', 'third'], $slugs);
    }

    public function test_items_are_paginated(): void
    {
        [$league] = $this->leagueWithBucket('Items Pagination League');

        foreach (['a', 'b', 'c', 'd', 'e'] as $tradeId) {
            $this->categorizedItem($league, $tradeId, 'Pages');
        }

        $second = $this->getJson("/api/leagues/{$league->slug}/items?category=Pages&sort=name&perPage=2&page=2")->assertOk()->json();
        $last = $this->getJson("/api/leagues/{$league->slug}/items?category=Pages&sort=name&perPage=2&page=3")->json();
        $beyond = $this->getJson("/api/leagues/{$league->slug}/items?category=Pages&sort=name&perPage=2&page=4")->json();

        $this->assertSame(['c', 'd'], $this->slugs($second['data']));
        $this->assertSame(
            ['currentPage' => 2, 'lastPage' => 3, 'perPage' => 2, 'total' => 5, 'currency' => 'divine', 'lastBucket' => '2030-01-10T12:00:00+00:00', 'lastSnapshotAt' => null],
            $second['meta'],
        );
        $this->assertSame(['e'], $this->slugs($last['data']));
        $this->assertSame([], $beyond['data']);
        $this->assertSame(5, $beyond['meta']['total']);
    }

    public function test_items_of_other_leagues_are_not_listed(): void
    {
        [$league] = $this->leagueWithBucket('Items Own League');
        [$other] = $this->leagueWithBucket('Items Foreign League');
        $this->categorizedItem($other, 'foreign', 'Currency');

        $slugs = $this->slugs($this->getJson("/api/leagues/{$league->slug}/items")->json('data'));

        $this->assertNotContains('foreign', $slugs);
    }

    public function test_items_are_measured_in_the_requested_currency(): void
    {
        [$league, $divineMetrics] = $this->leagueWithBucket('Items Currency League');
        $chaosMetrics = $this->metricsItem($league, $this->item($league, 'chaos'));
        ExchangeMarketMetric::factory()->forMetricsItem($chaosMetrics)->atBucket('2030-01-10 00:00:00')->create();
        $item = $this->categorizedItem($league, 'priced', 'Currency');
        $this->metric($divineMetrics, $item, self::LAST_BUCKET, ['price' => 2.5]);
        $this->metric($chaosMetrics, $item, '2030-01-10 00:00:00', ['price' => 200.5]);

        $default = $this->getJson("/api/leagues/{$league->slug}/items?category=Currency")->json();
        $chaos = $this->getJson("/api/leagues/{$league->slug}/items?category=Currency&currency=chaos")->json();

        $this->assertSame('divine', $default['meta']['currency']);
        $this->assertSame(2.5, $default['data'][0]['price']);
        $this->assertSame('chaos', $chaos['meta']['currency']);
        $this->assertSame(200.5, $chaos['data'][0]['price']);
        $this->assertSame('2030-01-10T00:00:00+00:00', $chaos['meta']['lastBucket']);
    }

    public function test_unknown_currency_is_rejected(): void
    {
        [$league] = $this->leagueWithBucket('Items Unknown Currency League');

        $this->getJson("/api/leagues/{$league->slug}/items?currency=nothing")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'unknown sort field' => ['sort=bogus'],
            'sort of the wrong field after a minus' => ['sort=-bogus'],
            'page below one' => ['page=0'],
            'page not a number' => ['page=abc'],
            'per page below one' => ['perPage=0'],
            'per page above the maximum' => ['perPage=101'],
            'search too long' => ['search='.str_repeat('x', 101)],
            'uncategorized not a boolean' => ['uncategorized=maybe'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_queries_are_rejected(string $query): void
    {
        [$league] = $this->leagueWithBucket('Items Invalid League');

        $this->getJson("/api/leagues/{$league->slug}/items?{$query}")->assertUnprocessable();
    }

    public function test_a_non_default_items_query_is_not_cached(): void
    {
        [$league] = $this->leagueWithBucket('Items Not Cached League');
        $this->getJson("/api/leagues/{$league->slug}/items?category=Fresh")->assertOk()->assertJsonPath('meta.total', 0);

        $this->categorizedItem($league, 'late', 'Fresh');

        $this->getJson("/api/leagues/{$league->slug}/items?category=Fresh")->assertJsonPath('meta.total', 1);
    }

    /**
     * Each query differs from the default one in a single parameter, and the item
     * added between the two requests is visible under it.
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function nonDefaultQueries(): array
    {
        return [
            'search' => ['search=Ore', null],
            'category' => ['category=Currency', 'Currency'],
            'uncategorized' => ['uncategorized=1', null],
            'another sort field' => ['sort=price', 'Currency'],
            'another direction' => ['sort=units', 'Currency'],
            'another page' => ['page=2', 'Currency'],
            'another per page' => ['perPage=10', 'Currency'],
            'another currency' => ['currency=chaos', 'Currency'],
        ];
    }

    #[DataProvider('nonDefaultQueries')]
    public function test_a_non_default_query_is_never_cached(string $query, ?string $category): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Not Cached Query League');
        $chaosMetrics = $this->metricsItem($league, $this->item($league, 'chaos'));
        ExchangeMarketMetric::factory()->forMetricsItem($chaosMetrics)->atBucket(self::LAST_BUCKET)->create();
        $this->metric($metricsItem, $this->categorizedItem($league, 'first', 'Currency'), self::LAST_BUCKET);

        $before = $this->getJson("/api/leagues/{$league->slug}/items?{$query}")->assertOk()->json('meta.total');

        $this->categorizedItem($league, 'oreblight', $category, 'Oreblight');

        $this->getJson("/api/leagues/{$league->slug}/items?{$query}")->assertJsonPath('meta.total', $before + 1);
    }

    public function test_the_default_items_query_is_cached_until_the_league_is_flushed(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Cached Default League');
        $this->metric($metricsItem, $this->categorizedItem($league, 'first', 'Currency'), self::LAST_BUCKET);

        $this->getJson("/api/leagues/{$league->slug}/items")->assertOk()->assertJsonPath('meta.total', 2);

        $this->metric($metricsItem, $this->categorizedItem($league, 'second', 'Currency'), self::LAST_BUCKET);

        $this->getJson("/api/leagues/{$league->slug}/items")->assertJsonPath('meta.total', 2);

        Cache::tags([BucketMetricsTagEnum::bucket_metrics_league_id->name.$league->id])->flush();

        $this->getJson("/api/leagues/{$league->slug}/items")->assertJsonPath('meta.total', 3);
    }

    public function test_the_default_items_query_is_cached_per_league(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Cached League');
        [$other, $otherMetricsItem] = $this->leagueWithBucket('Items Other Cached League');
        $this->metric($metricsItem, $this->categorizedItem($league, 'one', 'Currency'), self::LAST_BUCKET);
        $this->metric($otherMetricsItem, $this->categorizedItem($other, 'one', 'Currency'), self::LAST_BUCKET);
        $this->getJson("/api/leagues/{$league->slug}/items")->assertJsonPath('meta.total', 2);

        $this->metric($otherMetricsItem, $this->categorizedItem($other, 'two', 'Currency'), self::LAST_BUCKET);

        $this->getJson("/api/leagues/{$league->slug}/items")->assertJsonPath('meta.total', 2);
        $this->getJson("/api/leagues/{$other->slug}/items")->assertJsonPath('meta.total', 3);
    }

    public function test_the_cached_default_page_keeps_its_rows_and_sparklines(): void
    {
        [$league, $metricsItem] = $this->leagueWithBucket('Items Cached Shape League');
        $item = $this->categorizedItem($league, 'priced', 'Currency');
        $this->metric($metricsItem, $item, self::LAST_BUCKET, ['price' => 2.5, 'units' => 100.0]);
        $first = $this->getJson("/api/leagues/{$league->slug}/items")->assertOk()->json('data.0');

        $second = $this->getJson("/api/leagues/{$league->slug}/items")->assertOk()->json('data.0');

        $this->assertSame($first, $second);
        $this->assertSame('priced', $second['slug']);
        $this->assertSame(2.5, $second['price']);
        $this->assertSame([['bucket' => '2030-01-10T12:00:00+00:00', 'value' => 2.5]], $second['sparkline']);
    }

    public function test_categories_are_distinct_sorted_and_skip_empty_values(): void
    {
        $league = $this->league('Items Categories League');
        $this->categorizedItem($league, 'one', 'Scarabs');
        $this->categorizedItem($league, 'two', 'Currency');
        $this->categorizedItem($league, 'three', 'Currency');
        $this->categorizedItem($league, 'four', '');

        $this->getJson("/api/leagues/{$league->slug}/items/categories")
            ->assertOk()
            ->assertExactJson(['categories' => ['Currency', 'Scarabs'], 'hasUncategorized' => true]);
    }

    public function test_categories_report_no_uncategorized_items_when_all_have_one(): void
    {
        $league = $this->league('Items All Categorized League');
        $this->categorizedItem($league, 'one', 'Currency');

        $this->getJson("/api/leagues/{$league->slug}/items/categories")
            ->assertOk()
            ->assertExactJson(['categories' => ['Currency'], 'hasUncategorized' => false]);
    }

    public function test_categories_of_a_league_without_items_are_empty(): void
    {
        $league = $this->league('Items Empty Categories League');

        $this->getJson("/api/leagues/{$league->slug}/items/categories")
            ->assertOk()
            ->assertExactJson(['categories' => [], 'hasUncategorized' => false]);
    }

    public function test_categories_do_not_include_other_leagues(): void
    {
        $league = $this->league('Items Own Categories League');
        $other = $this->league('Items Other Categories League');
        $this->categorizedItem($league, 'own', 'Currency');
        $this->categorizedItem($other, 'foreign', 'Foreign');

        $this->getJson("/api/leagues/{$league->slug}/items/categories")->assertJsonPath('categories', ['Currency']);
    }

    public function test_categories_are_cached_until_the_league_tag_is_flushed(): void
    {
        $league = $this->league('Items Cached Categories League');
        $other = $this->league('Items Other Cached League');
        $this->categorizedItem($league, 'one', 'Currency');
        $this->categorizedItem($other, 'one', 'Currency');
        $this->getJson("/api/leagues/{$league->slug}/items/categories")->assertOk();
        $this->getJson("/api/leagues/{$other->slug}/items/categories")->assertOk();

        $this->categorizedItem($league, 'two', 'Scarabs');
        $this->categorizedItem($other, 'two', 'Scarabs');

        $this->getJson("/api/leagues/{$league->slug}/items/categories")->assertJsonPath('categories', ['Currency']);

        Cache::tags([ItemCategoryTagEnum::item_categories_league_id->name.$league->id])->flush();

        $this->getJson("/api/leagues/{$league->slug}/items/categories")->assertJsonPath('categories', ['Currency', 'Scarabs']);
        $this->getJson("/api/leagues/{$other->slug}/items/categories")->assertJsonPath('categories', ['Currency']);
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

    private function beta(ExchangeMetricsItem $metricsItem, ExchangeItem $item, string $calculatedAt, float $beta): ExchangeItemBeta
    {
        return ExchangeItemBeta::factory()
            ->forItem($item, $metricsItem)
            ->calculatedAt($calculatedAt)
            ->withBeta($beta)
            ->create();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function slugs(array $rows): array
    {
        return array_column($rows, 'slug');
    }
}
