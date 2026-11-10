<?php

namespace Tests\Feature;

use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ExchangePriceTagEnum;
use App\Enums\Tags\ItemCategoryTagEnum;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\PoeLeague;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class LeagueMarketRouteFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private const string LAST_BUCKET = '2030-01-10 12:00:00';

    private const string PREVIOUS_BUCKET = '2030-01-10 00:00:00';

    public function test_unknown_and_inactive_leagues_are_not_found(): void
    {
        $league = $this->league('Market Inactive League');
        $league->update(['is_active' => false]);

        $this->getJson('/api/leagues/no-such-league/market/distribution')->assertNotFound();
        $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertNotFound();
    }

    public function test_an_unknown_currency_is_rejected(): void
    {
        [$league] = $this->marketLeague('Market Currency League');

        $this->getJson("/api/leagues/{$league->slug}/market/distribution?currency=mirror")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_an_unknown_bucket_is_rejected(): void
    {
        [$league] = $this->marketLeague('Market Bucket League');

        $this->getJson("/api/leagues/{$league->slug}/market/distribution?bucket=2030-01-09T12:00:00Z")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bucket');
        $this->getJson("/api/leagues/{$league->slug}/market/distribution?bucket=not-a-date")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bucket');
    }

    public function test_a_league_without_metrics_items_has_an_empty_distribution(): void
    {
        $league = $this->league('Market No Currencies League');

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution?bucket=2030-01-09T12:00:00Z")
            ->assertOk()
            ->json();

        $this->assertNull($json['currency']);
        $this->assertNull($json['bucket']);
        $this->assertSame([], $json['buckets']);
        $this->assertSame(['up' => 0, 'down' => 0, 'flat' => 0], $json['breadth']);
        $this->assertSame([[], [], []], [$json['bins'], $json['byCategory'], $json['items']]);
    }

    public function test_a_league_without_buckets_has_an_empty_distribution_with_a_currency(): void
    {
        $league = $this->league('Market No Buckets League');
        $this->metricsItem($league, $this->item($league, 'divine'));

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();

        $this->assertSame('divine', $json['currency']);
        $this->assertNull($json['bucket']);
        $this->assertNull($json['marketReturn']);
        $this->assertNull($json['median']);
        $this->assertSame([], $json['items']);

        $this->getJson("/api/leagues/{$league->slug}/market/distribution?bucket=2030-01-10T12:00:00Z")->assertUnprocessable();
    }

    public function test_the_distribution_has_the_documented_shape_and_numeric_values(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Shape League');
        $this->bucketItem($league, $metricsItem, 'item-a', self::LAST_BUCKET, 0.03, category: 'Currency', units: 12.5, returnWeight: 1.0);

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();

        $this->assertSame(
            ['currency', 'bucket', 'buckets', 'marketReturn', 'median', 'breadth', 'bins', 'byCategory', 'items'],
            array_keys($json),
        );
        $this->assertSame('divine', $json['currency']);
        $this->assertSame('2030-01-10T12:00:00+00:00', $json['bucket']);
        $this->assertSame(['2030-01-10T12:00:00+00:00', '2030-01-10T00:00:00+00:00'], $json['buckets']);
        $this->assertSame(0.011, $json['marketReturn']);
        $this->assertSame(0.03, $json['median']);
        $this->assertSame(['up' => 1, 'down' => 0, 'flat' => 0], $json['breadth']);
        $this->assertSame([['category' => 'Currency', 'median' => 0.03, 'count' => 1]], $json['byCategory']);
        $this->assertEquals(
            [['slug' => 'item-a', 'name' => 'ITEM-A', 'category' => 'Currency', 'price' => 1.0, 'logReturn' => 0.03, 'units' => 12.5, 'returnWeight' => 1.0]],
            $json['items'],
        );
    }

    public function test_bins_cover_the_whole_range_and_add_up_to_the_items(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Bins League');

        foreach ([-0.4, -0.055, 0.0, 0.004, 0.0455, 0.35] as $index => $logReturn) {
            $this->bucketItem($league, $metricsItem, "item-{$index}", self::LAST_BUCKET, $logReturn);
        }

        $bins = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json('bins');

        $this->assertCount(32, $bins);
        $this->assertSame(['from' => null, 'to' => -0.3, 'count' => 1], $bins[0]);
        $this->assertSame(['from' => 0.3, 'to' => null, 'count' => 1], $bins[31]);
        $this->assertSame(['from' => -0.06, 'to' => -0.04, 'count' => 1], $bins[13]);
        $this->assertEquals(['from' => 0.0, 'to' => 0.02, 'count' => 2], $bins[16]);
        $this->assertSame(['from' => 0.04, 'to' => 0.06, 'count' => 1], $bins[18]);
        $this->assertSame(6, array_sum(array_column($bins, 'count')));
    }

    public function test_breadth_and_median_follow_the_flat_threshold(): void
    {
        config(['poe.market.flat_threshold' => 0.01]);
        [$league, $metricsItem] = $this->marketLeague('Market Breadth League');

        foreach ([0.2, 0.01, 0.009, -0.009, -0.01, -0.2, -0.4] as $index => $logReturn) {
            $this->bucketItem($league, $metricsItem, "item-{$index}", self::LAST_BUCKET, $logReturn);
        }

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();

        $this->assertSame(['up' => 2, 'down' => 3, 'flat' => 2], $json['breadth']);
        $this->assertSame(-0.009, $json['median']);
    }

    public function test_items_are_sorted_from_the_best_return_to_the_worst(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Sort League');
        $this->bucketItem($league, $metricsItem, 'item-b', self::LAST_BUCKET, 0.05);
        $this->bucketItem($league, $metricsItem, 'item-low', self::LAST_BUCKET, -0.05);
        $this->bucketItem($league, $metricsItem, 'item-a', self::LAST_BUCKET, 0.05);
        $this->bucketItem($league, $metricsItem, 'item-top', self::LAST_BUCKET, 0.2);

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();

        $this->assertSame(['item-top', 'item-a', 'item-b', 'item-low'], array_column($json['items'], 'slug'));
    }

    public function test_items_without_a_return_are_left_out(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market No Return League');
        $this->bucketItem($league, $metricsItem, 'with-return', self::LAST_BUCKET, 0.05);
        $this->bucketItem($league, $metricsItem, 'no-return', self::LAST_BUCKET, null);

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();

        $this->assertSame(['with-return'], array_column($json['items'], 'slug'));
        $this->assertSame(['up' => 1, 'down' => 0, 'flat' => 0], $json['breadth']);
    }

    public function test_thinly_traded_items_are_counted_only_when_all_is_asked_for(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Weight League');
        $this->bucketItem($league, $metricsItem, 'traded', self::LAST_BUCKET, 0.05, returnWeight: 1.0);
        $this->bucketItem($league, $metricsItem, 'unknown-volume', self::LAST_BUCKET, 0.04, returnWeight: null);
        $this->bucketItem($league, $metricsItem, 'thin', self::LAST_BUCKET, 0.03, returnWeight: 0.0);

        $reliable = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();
        $explicit = $this->getJson("/api/leagues/{$league->slug}/market/distribution?all=0")->assertOk()->json();
        $all = $this->getJson("/api/leagues/{$league->slug}/market/distribution?all=1")->assertOk()->json();

        $this->assertSame(['traded', 'unknown-volume'], array_column($reliable['items'], 'slug'));
        $this->assertSame($reliable, $explicit);
        $this->assertSame(['traded', 'unknown-volume', 'thin'], array_column($all['items'], 'slug'));
        $this->assertSame(3, $all['breadth']['up']);
    }

    public function test_the_asked_bucket_is_used_instead_of_the_latest(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Pick Bucket League');
        $this->bucketItem($league, $metricsItem, 'old-item', self::PREVIOUS_BUCKET, 0.05);
        $this->bucketItem($league, $metricsItem, 'new-item', self::LAST_BUCKET, 0.06);

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution?bucket=2030-01-10T00:00:00%2B00:00")
            ->assertOk()
            ->json();
        $shifted = $this->getJson("/api/leagues/{$league->slug}/market/distribution?bucket=2030-01-10T03:00:00%2B03:00")
            ->assertOk()
            ->json();

        $this->assertSame('2030-01-10T00:00:00+00:00', $json['bucket']);
        $this->assertSame(0.002, $json['marketReturn']);
        $this->assertSame(['old-item'], array_column($json['items'], 'slug'));
        $this->assertSame($json, $shifted);
    }

    public function test_buckets_are_limited_by_config_and_an_older_bucket_can_still_be_asked_for(): void
    {
        config(['poe.market.buckets_limit' => 1]);
        [$league, $metricsItem] = $this->marketLeague('Market Buckets Limit League');
        $this->bucketItem($league, $metricsItem, 'old-item', self::PREVIOUS_BUCKET, 0.05);

        $latest = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();
        $older = $this->getJson("/api/leagues/{$league->slug}/market/distribution?bucket=2030-01-10T00:00:00Z")
            ->assertOk()
            ->json();

        $this->assertSame(['2030-01-10T12:00:00+00:00'], $latest['buckets']);
        $this->assertSame('2030-01-10T00:00:00+00:00', $older['bucket']);
        $this->assertSame(['old-item'], array_column($older['items'], 'slug'));
    }

    public function test_categories_have_a_median_a_count_and_the_uncategorized_ones_come_last_among_equals(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Categories League');
        $this->bucketItem($league, $metricsItem, 'currency-a', self::LAST_BUCKET, 0.04, category: 'Currency');
        $this->bucketItem($league, $metricsItem, 'currency-b', self::LAST_BUCKET, 0.02, category: 'Currency');
        $this->bucketItem($league, $metricsItem, 'currency-c', self::LAST_BUCKET, 0.09, category: 'Currency');
        $this->bucketItem($league, $metricsItem, 'map-a', self::LAST_BUCKET, -0.1, category: 'Maps');
        $this->bucketItem($league, $metricsItem, 'blank', self::LAST_BUCKET, 0.04, category: '');
        $this->bucketItem($league, $metricsItem, 'none', self::LAST_BUCKET, 0.04);
        $this->bucketItem($league, $metricsItem, 'fragment', self::LAST_BUCKET, 0.04, category: 'Fragments');

        $json = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk()->json();

        $this->assertSame(
            [
                ['category' => 'Currency', 'median' => 0.04, 'count' => 3],
                ['category' => 'Fragments', 'median' => 0.04, 'count' => 1],
                ['category' => null, 'median' => 0.04, 'count' => 2],
                ['category' => 'Maps', 'median' => -0.1, 'count' => 1],
            ],
            $json['byCategory'],
        );
        $this->assertNull(collect($json['items'])->firstWhere('slug', 'blank')['category']);
    }

    public function test_the_distribution_is_cached_between_requests(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Cached League');
        $this->bucketItem($league, $metricsItem, 'item-a', self::LAST_BUCKET, 0.05);

        $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk();
        $this->bucketItem($league, $metricsItem, 'late-item', self::LAST_BUCKET, 0.4);

        $this->assertSame(
            ['item-a'],
            array_column($this->getJson("/api/leagues/{$league->slug}/market/distribution")->json('items'), 'slug'),
        );
    }

    public function test_different_parameters_are_cached_separately(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Cache Keys League');
        $this->bucketItem($league, $metricsItem, 'traded', self::LAST_BUCKET, 0.05, returnWeight: 1.0);
        $this->bucketItem($league, $metricsItem, 'thin', self::LAST_BUCKET, 0.04, returnWeight: 0.0);

        $reliable = $this->getJson("/api/leagues/{$league->slug}/market/distribution")->json('items');
        $all = $this->getJson("/api/leagues/{$league->slug}/market/distribution?all=1")->json('items');

        $this->assertCount(1, $reliable);
        $this->assertCount(2, $all);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function marketInvalidationTags(): array
    {
        return [
            'new buckets' => [BucketMetricsTagEnum::bucket_metrics_league_id->name],
            'recalculated betas' => [BetasMetricsTagEnum::betas_metrics_league_id->name],
            'imported prices' => [ExchangePriceTagEnum::price_league_id->name],
            'changed categories' => [ItemCategoryTagEnum::item_categories_league_id->name],
        ];
    }

    #[DataProvider('marketInvalidationTags')]
    public function test_flushing_a_league_tag_drops_the_cached_distribution(string $tagName): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Flush League');
        $this->bucketItem($league, $metricsItem, 'item-a', self::LAST_BUCKET, 0.05);
        $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk();
        $this->bucketItem($league, $metricsItem, 'late-item', self::LAST_BUCKET, 0.4);

        Cache::tags([$tagName.$league->id])->flush();

        $this->assertSame(
            ['late-item', 'item-a'],
            array_column($this->getJson("/api/leagues/{$league->slug}/market/distribution")->json('items'), 'slug'),
        );
    }

    public function test_flushing_another_league_does_not_drop_the_cached_distribution(): void
    {
        [$league, $metricsItem] = $this->marketLeague('Market Kept League');
        [$other] = $this->marketLeague('Market Other League');
        $this->bucketItem($league, $metricsItem, 'item-a', self::LAST_BUCKET, 0.05);
        $this->getJson("/api/leagues/{$league->slug}/market/distribution")->assertOk();
        $this->bucketItem($league, $metricsItem, 'late-item', self::LAST_BUCKET, 0.4);

        Cache::tags([BucketMetricsTagEnum::bucket_metrics_league_id->name.$other->id])->flush();

        $this->assertSame(
            ['item-a'],
            array_column($this->getJson("/api/leagues/{$league->slug}/market/distribution")->json('items'), 'slug'),
        );
    }

    /**
     * A league with the divine metrics item and two buckets, the latest one with 0.011 market return.
     *
     * @return array{0: PoeLeague, 1: ExchangeMetricsItem}
     */
    private function marketLeague(string $name): array
    {
        $league = $this->league($name);
        $metricsItem = $this->metricsItem($league, $this->item($league, 'divine'));

        foreach ([[self::PREVIOUS_BUCKET, 0.002], [self::LAST_BUCKET, 0.011]] as [$bucketStart, $marketReturn]) {
            ExchangeMarketMetric::factory()
                ->forMetricsItem($metricsItem)
                ->atBucket($bucketStart)
                ->withReturn($marketReturn)
                ->create();
        }

        return [$league, $metricsItem];
    }

    private function bucketItem(
        PoeLeague $league,
        ExchangeMetricsItem $metricsItem,
        string $tradeId,
        string $bucketStart,
        ?float $logReturn,
        ?string $category = null,
        ?float $units = null,
        ?float $returnWeight = null,
    ): ExchangeItemMetric {
        $item = ExchangeItem::query()
            ->where('poe_league_id', $league->id)
            ->where('trade_id', $tradeId)
            ->first() ?? $this->item($league, $tradeId);

        if ($category !== null) {
            $item->update(['poe_category' => $category]);
        }

        return ExchangeItemMetric::factory()
            ->forItem($item, $metricsItem)
            ->atBucket($bucketStart)
            ->state(['log_return' => $logReturn, 'units' => $units, 'return_weight' => $returnWeight])
            ->create();
    }
}
