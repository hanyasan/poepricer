<?php

namespace Tests\Feature;

use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class LeagueRouteFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    public function test_it_returns_a_flat_json_array_of_leagues(): void
    {
        $this->leagueWithDivineMetrics('Route Flat League', [['2030-01-01 00:00:00', 0.01, 3]]);

        $response = $this->getJson('/api/leagues');

        $response->assertOk();
        $this->assertTrue(array_is_list($response->json()));
        $this->assertNull($response->json('data'));
    }

    public function test_it_returns_the_league_fields_in_camel_case(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Fields League', [
            ['2030-01-01 00:00:00', 0.0132, 40],
            ['2030-01-01 12:00:00', -0.0048, 42],
        ]);
        $this->snapshot($league, $this->exchangeType('route-fields-type'), '2030-01-01 12:10:00', ['divine' => 1.0]);

        $row = $this->rowOf($league);

        $this->assertSame(
            ['game', 'slug', 'name', 'isActive', 'lastSnapshotAt', 'basketSize', 'marketReturnSeries'],
            array_keys($row),
        );
        $this->assertSame('poe2', $row['game']);
        $this->assertSame('Route Fields League', $row['name']);
        $this->assertTrue($row['isActive']);
    }

    public function test_slug_is_the_stored_league_slug(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Slug League', [['2030-01-01 00:00:00', 0.01, 1]]);

        $this->assertSame($league->slug, $this->rowOf($league)['slug']);
    }

    public function test_series_is_ordered_by_bucket_ascending_with_numeric_values(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Series League', [
            ['2030-01-02 00:00:00', -0.0048, 42],
            ['2030-01-01 00:00:00', 0.0132, 40],
        ]);

        $row = $this->rowOf($league);

        $this->assertSame(
            [
                ['bucket' => '2030-01-01T00:00:00+00:00', 'value' => 0.0132],
                ['bucket' => '2030-01-02T00:00:00+00:00', 'value' => -0.0048],
            ],
            $row['marketReturnSeries'],
        );
    }

    public function test_basket_size_is_an_integer_taken_from_the_latest_bucket(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Basket League', [
            ['2030-01-01 00:00:00', 0.01, 40],
            ['2030-01-02 00:00:00', 0.02, 42],
        ]);

        $this->assertSame(42, $this->rowOf($league)['basketSize']);
    }

    public function test_last_snapshot_at_is_iso_8601_with_timezone(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Snapshot League', [['2030-01-01 00:00:00', 0.01, 1]]);
        $type = $this->exchangeType('route-snapshot-type');
        $this->snapshot($league, $type, '2030-01-01 11:00:00', ['divine' => 1.0]);
        $this->snapshot($league, $type, '2030-01-01 12:10:00', ['divine' => 1.0]);

        $this->assertSame('2030-01-01T12:10:00+00:00', $this->rowOf($league)['lastSnapshotAt']);
    }

    public function test_last_snapshot_at_is_null_without_price_checks(): void
    {
        $league = $this->leagueWithDivineMetrics('Route No Snapshot League', [['2030-01-01 00:00:00', 0.01, 1]]);

        $this->assertNull($this->rowOf($league)['lastSnapshotAt']);
    }

    public function test_series_ignores_metrics_of_other_currencies(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Currency League', [['2030-01-01 00:00:00', 0.01, 5]]);
        $chaos = $this->metricsItem($league, $this->item($league, 'chaos'));
        ExchangeMarketMetric::factory()->forMetricsItem($chaos)->atBucket('2030-01-02 00:00:00')->withReturn(0.5)->create();

        $series = $this->rowOf($league)['marketReturnSeries'];

        $this->assertCount(1, $series);
        $this->assertSame(0.01, $series[0]['value']);
    }

    public function test_inactive_leagues_are_not_returned(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Inactive League', [['2030-01-01 00:00:00', 0.01, 1]]);
        $league->update(['is_active' => false]);

        $names = array_column($this->getJson('/api/leagues')->assertOk()->json(), 'name');

        $this->assertNotContains('Route Inactive League', $names);
    }

    public function test_league_without_a_series_in_the_window_is_not_returned(): void
    {
        $other = $this->league('Route Other Currency League');
        $chaos = $this->metricsItem($other, $this->item($other, 'chaos'));
        ExchangeMarketMetric::factory()->forMetricsItem($chaos)->atBucket('2030-01-01 00:00:00')->create();
        $stale = $this->leagueWithDivineMetrics('Route Stale League', [[now()->subDays(30)->toDateTimeString(), 0.01, 1]]);

        $names = array_column($this->getJson('/api/leagues')->assertOk()->json(), 'name');

        $this->assertNotContains($other->league_name, $names);
        $this->assertNotContains($stale->league_name, $names);
    }

    public function test_series_contains_only_the_last_seven_days(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Window League', [
            [now()->subDays(9)->toDateTimeString(), 0.01, 1],
            [now()->subDays(2)->toDateTimeString(), 0.02, 2],
        ]);

        $series = $this->rowOf($league)['marketReturnSeries'];

        $this->assertCount(1, $series);
        $this->assertSame(0.02, $series[0]['value']);
    }

    public function test_leagues_are_sorted_by_game(): void
    {
        $poe2 = $this->leagueWithDivineMetrics('Route Sort Poe2 League', [['2030-01-01 00:00:00', 0.01, 1]]);
        $poe1 = $this->leagueWithDivineMetrics('Route Sort Poe1 League', [['2030-01-01 00:00:00', 0.01, 1]]);
        $poe1->update(['poe_version_id' => (int) PoeVersion::query()->where('version', 'poe1')->value('id')]);

        $names = array_values(array_intersect(
            array_column($this->getJson('/api/leagues')->assertOk()->json(), 'name'),
            [$poe1->league_name, $poe2->league_name],
        ));

        $this->assertSame([$poe1->league_name, $poe2->league_name], $names);
    }

    public function test_the_response_is_cached_between_requests(): void
    {
        $league = $this->leagueWithDivineMetrics('Route Cached League', [['2030-01-01 00:00:00', 0.01, 1]]);

        $this->getJson('/api/leagues')->assertOk();
        $league->update(['league_name' => 'Route Cached League Renamed']);

        $names = array_column($this->getJson('/api/leagues')->assertOk()->json(), 'name');

        $this->assertContains('Route Cached League', $names);
        $this->assertNotContains('Route Cached League Renamed', $names);
    }

    /**
     * @param  array<int, array{0: string, 1: float, 2: int}>  $buckets  bucket start, market return, basket size
     */
    private function leagueWithDivineMetrics(string $name, array $buckets): PoeLeague
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

        return $league;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowOf(PoeLeague $league): array
    {
        $rows = $this->getJson('/api/leagues')->assertOk()->json();

        foreach ($rows as $row) {
            if ($row['name'] === $league->league_name) {
                return $row;
            }
        }

        $this->fail("League [{$league->league_name}] is missing from the response.");
    }
}
