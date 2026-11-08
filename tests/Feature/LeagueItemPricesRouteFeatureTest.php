<?php

namespace Tests\Feature;

use App\Enums\Tags\ExchangePriceTagEnum;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\PoeLeague;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class LeagueItemPricesRouteFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    public function test_unknown_league_is_not_found(): void
    {
        $this->getJson('/api/leagues/no-such-league/items/exalted/prices')->assertNotFound();
    }

    public function test_inactive_league_is_not_found(): void
    {
        [$league] = $this->leagueWithPrices('Prices Inactive League');
        $league->update(['is_active' => false]);

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")->assertNotFound();
    }

    public function test_unknown_item_is_not_found(): void
    {
        [$league] = $this->leagueWithPrices('Prices Unknown Item League');

        $this->getJson("/api/leagues/{$league->slug}/items/no-such-item/prices")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Item not found']);
    }

    public function test_item_of_another_league_is_not_found(): void
    {
        [$league] = $this->leagueWithPrices('Prices Own League');
        $other = $this->league('Prices Other League');
        $this->item($other, 'mirror');

        $this->getJson("/api/leagues/{$league->slug}/items/mirror/prices")->assertNotFound();
    }

    public function test_unknown_window_is_rejected(): void
    {
        [$league] = $this->leagueWithPrices('Prices Window League');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?window=1y")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('window');
    }

    public function test_prices_have_the_documented_shape_and_numeric_values(): void
    {
        [$league] = $this->leagueWithPrices('Prices Shape League');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")
            ->assertOk()
            ->assertJsonStructure([
                'currency', 'window', 'from', 'to',
                'currencies' => [['slug', 'name', 'points']],
                'points' => [['at', 'price', 'units']],
                'stats' => [
                    'current' => ['at', 'price', 'units'],
                    'min', 'max', 'median', 'averageUnits',
                    'changes' => [['hours', 'change']],
                ],
            ])
            ->assertJsonPath('currency', 'divine')
            ->assertJsonPath('window', '48h')
            ->assertJsonPath('from', '2030-01-08T12:00:00+00:00')
            ->assertJsonPath('to', '2030-01-10T12:00:00+00:00')
            ->assertJsonPath('currencies.0', ['slug' => 'divine', 'name' => 'DIVINE', 'points' => 3])
            ->assertJsonPath('currencies.1', ['slug' => 'chaos', 'name' => 'CHAOS', 'points' => 2])
            ->assertJsonPath('points.0.at', '2030-01-10T00:00:00+00:00');
    }

    public function test_points_are_priced_in_the_currency_of_the_same_core_and_ordered_by_time(): void
    {
        [$league] = $this->leagueWithPrices('Prices Points League');

        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")->assertOk()->json();

        $this->assertSame(
            ['2030-01-10T00:00:00+00:00', '2030-01-10T06:00:00+00:00', '2030-01-10T12:00:00+00:00'],
            array_column($json['points'], 'at'),
        );
        $this->assertEqualsWithDelta([0.5, 0.6, 0.55], array_column($json['points'], 'price'), 1e-9);
        $this->assertIsFloat($json['points'][0]['price']);

        $chaos = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?currency=chaos")->assertOk()->json();

        $this->assertSame('chaos', $chaos['currency']);
        $this->assertSame(
            ['2030-01-10T00:00:00+00:00', '2030-01-10T06:00:00+00:00'],
            array_column($chaos['points'], 'at'),
        );
        $this->assertEqualsWithDelta([50.0, 72.0], array_column($chaos['points'], 'price'), 1e-9);
    }

    public function test_units_are_volume_per_rate_and_an_unknown_volume_counts_as_zero(): void
    {
        [$league] = $this->leagueWithPrices('Prices Units League');

        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")->assertOk()->json();

        $this->assertEqualsWithDelta([100.0, 0.0, 100.0], array_column($json['points'], 'units'), 1e-9);
    }

    public function test_the_currency_with_the_most_points_is_the_default_and_an_unavailable_one_falls_back_to_it(): void
    {
        [$league] = $this->leagueWithPrices('Prices Fallback League');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?currency=no-such")
            ->assertOk()
            ->assertJsonPath('currency', 'divine');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?currency=chaos")
            ->assertOk()
            ->assertJsonPath('currency', 'chaos');
    }

    public function test_the_item_is_not_priced_in_itself(): void
    {
        [$league] = $this->leagueWithPrices('Prices Self League');

        $json = $this->getJson("/api/leagues/{$league->slug}/items/chaos/prices")->assertOk()->json();

        $this->assertSame(['divine'], array_column($json['currencies'], 'slug'));
    }

    public function test_the_window_is_counted_back_from_the_last_price_check_of_the_league(): void
    {
        [$league] = $this->leagueWithPrices('Prices Window Bounds League');

        $sixHours = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?window=6h")->assertOk()->json();

        $this->assertSame('2030-01-10T06:00:00+00:00', $sixHours['from']);
        $this->assertSame(
            ['2030-01-10T06:00:00+00:00', '2030-01-10T12:00:00+00:00'],
            array_column($sixHours['points'], 'at'),
        );
        $this->assertSame(['divine' => 2, 'chaos' => 1], array_column($sixHours['currencies'], 'points', 'slug'));

        $week = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?window=7d")->assertOk()->json();

        $this->assertCount(4, $week['points']);
        $this->assertSame('2030-01-08T00:00:00+00:00', $week['points'][0]['at']);
    }

    public function test_stats_summarize_the_points(): void
    {
        [$league] = $this->leagueWithPrices('Prices Stats League');

        $stats = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")->assertOk()->json('stats');

        $this->assertSame('2030-01-10T12:00:00+00:00', $stats['current']['at']);
        $this->assertEqualsWithDelta(0.55, $stats['current']['price'], 1e-9);
        $this->assertEqualsWithDelta(100.0, $stats['current']['units'], 1e-9);
        $this->assertEqualsWithDelta(0.5, $stats['min'], 1e-9);
        $this->assertEqualsWithDelta(0.6, $stats['max'], 1e-9);
        $this->assertEqualsWithDelta(0.55, $stats['median'], 1e-9);
        $this->assertEqualsWithDelta(200 / 3, $stats['averageUnits'], 1e-9);
    }

    public function test_changes_use_the_last_point_not_later_than_the_span_and_skip_spans_longer_than_the_window(): void
    {
        [$league] = $this->leagueWithPrices('Prices Changes League');

        $day = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?window=24h")->assertOk()->json('stats.changes');

        $this->assertSame([1, 6, 24], array_column($day, 'hours'));
        $this->assertEqualsWithDelta(0.55 / 0.6 - 1, $day[0]['change'], 1e-9);
        $this->assertEqualsWithDelta(0.55 / 0.6 - 1, $day[1]['change'], 1e-9);
        $this->assertNull($day[2]['change']);

        $twoDays = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")->assertOk()->json('stats.changes');

        $this->assertSame([1, 6, 24, 48], array_column($twoDays, 'hours'));
        $this->assertNull($twoDays[3]['change']);

        $week = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices?window=7d")->assertOk()->json('stats.changes');

        $this->assertEqualsWithDelta(0.55 / 0.4 - 1, $week[2]['change'], 1e-9);
        $this->assertEqualsWithDelta(0.55 / 0.4 - 1, $week[3]['change'], 1e-9);
    }

    public function test_a_league_without_price_checks_has_empty_lists(): void
    {
        $league = $this->league('Prices Empty League');
        $this->item($league, 'exalted');

        $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")
            ->assertOk()
            ->assertExactJson([
                'currency' => null,
                'window' => '48h',
                'from' => null,
                'to' => null,
                'currencies' => [],
                'points' => [],
                'stats' => null,
            ]);
    }

    public function test_an_item_without_prices_in_the_window_has_empty_lists_but_the_window_bounds(): void
    {
        [$league] = $this->leagueWithPrices('Prices No Item Prices League');
        $this->item($league, 'fragment');

        $this->getJson("/api/leagues/{$league->slug}/items/fragment/prices")
            ->assertOk()
            ->assertExactJson([
                'currency' => null,
                'window' => '48h',
                'from' => '2030-01-08T12:00:00+00:00',
                'to' => '2030-01-10T12:00:00+00:00',
                'currencies' => [],
                'points' => [],
                'stats' => null,
            ]);
    }

    public function test_prices_of_another_league_do_not_leak(): void
    {
        [$league] = $this->leagueWithPrices('Prices Leak Own League');
        [$other] = $this->leagueWithPrices('Prices Leak Other League');
        $this->snapshot($other, $this->exchangeType('type-leak'), '2030-01-10 18:00:00', ['exalted' => 9.0]);

        $json = $this->getJson("/api/leagues/{$league->slug}/items/exalted/prices")->assertOk()->json();

        $this->assertSame('2030-01-10T12:00:00+00:00', $json['to']);
        $this->assertCount(3, $json['points']);
    }

    public function test_the_response_is_cached_until_the_prices_are_imported_again(): void
    {
        [$league] = $this->leagueWithPrices('Prices Cache League');
        $url = "/api/leagues/{$league->slug}/items/exalted/prices";

        $first = $this->getJson($url)->assertOk()->json();

        DB::enableQueryLog();
        $second = $this->getJson($url)->assertOk()->json();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertSame([], array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'exchange_item_prices')));

        $this->snapshot($league, ExchangeType::query()->firstOrFail(), '2030-01-10 13:00:00', ['exalted' => 0.7]);
        $this->assertCount(3, $this->getJson($url)->json('points'));

        Cache::tags([
            ExchangePriceTagEnum::price_get->name,
            ExchangePriceTagEnum::price_league_id->name.$league->id,
        ])->flush();

        $this->assertCount(4, $this->getJson($url)->json('points'));
    }

    /**
     * A league with the items divine, chaos and exalted and four price checks of one core.
     * Exalted is priced at 0.4 divine on the 8th (no chaos in the core), then at 0.5, 0.6 and
     * 0.55 divine on the 10th at 00:00, 06:00 and 12:00. The core has chaos at the rate 100 and
     * 120 in the first two checks of the 10th only, and the chaos item is priced in the second one. The volume of the first and the
     * last check of the 10th is 50 and 55 divines, the middle one has none.
     *
     * @return array{PoeLeague}
     */
    private function leagueWithPrices(string $name): array
    {
        $league = $this->league($name);

        foreach (['divine', 'chaos', 'exalted'] as $tradeId) {
            $this->item($league, $tradeId);
        }

        $type = $this->exchangeType('type-prices-'.$league->id);

        $this->snapshot($league, $type, '2030-01-08 00:00:00', ['exalted' => 0.4]);
        $this->snapshot($league, $type, '2030-01-10 00:00:00', ['exalted' => 0.5], ['divine' => 1.0, 'chaos' => 100.0], ['exalted' => 50.0]);
        $this->snapshot($league, $type, '2030-01-10 06:00:00', ['exalted' => 0.6, 'chaos' => 0.01], ['divine' => 1.0, 'chaos' => 120.0]);
        $this->snapshot($league, $type, '2030-01-10 12:00:00', ['exalted' => 0.55], ['divine' => 1.0], ['exalted' => 55.0]);

        return [$league];
    }
}
