<?php

namespace Tests\Feature\Repositories\Poe\Exchange\Metrics;

use App\Repositories\Poe\Exchange\Metrics\ExchangeBucketPriceRepositoryContract;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class ExchangeBucketPriceRepositoryTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    public function test_it_multiplies_the_stored_rate_by_the_core_rate_of_the_currency(): void
    {
        $league = $this->league('Bucket Price League');
        $type = $this->exchangeType();
        $item = $this->item($league, 'item-a');

        $this->snapshot($league, $type, '2030-01-01 01:00:00', ['item-a' => 0.5], ['divine' => 1.0, 'exalted' => 600.0]);

        $inDivine = $this->prices($league->id, 'divine');
        $inExalted = $this->prices($league->id, 'exalted');

        $this->assertSame([$item->id => [['price' => 0.5, 'units' => null]]], $inDivine);
        $this->assertSame([$item->id => [['price' => 300.0, 'units' => null]]], $inExalted);
    }

    public function test_it_groups_the_prices_of_all_snapshots_by_item(): void
    {
        $league = $this->league('Bucket Group League');
        $type = $this->exchangeType();
        $itemA = $this->item($league, 'item-a');
        $itemB = $this->item($league, 'item-b');

        $this->snapshot($league, $type, '2030-01-01 01:00:00', ['item-a' => 1.0, 'item-b' => 5.0]);
        $this->snapshot($league, $type, '2030-01-01 02:00:00', ['item-a' => 2.0]);

        $prices = $this->prices($league->id, 'divine');

        $this->assertEqualsCanonicalizing([1.0, 2.0], array_column($prices[$itemA->id], 'price'));
        $this->assertSame([5.0], array_column($prices[$itemB->id], 'price'));
    }

    public function test_the_period_includes_its_start_and_excludes_its_end(): void
    {
        $league = $this->league('Bucket Border League');
        $type = $this->exchangeType();
        $item = $this->item($league, 'item-a');

        $this->snapshot($league, $type, '2029-12-31 23:59:59', ['item-a' => 1.0]);
        $this->snapshot($league, $type, '2030-01-01 00:00:00', ['item-a' => 2.0]);
        $this->snapshot($league, $type, '2030-01-01 11:59:59', ['item-a' => 3.0]);
        $this->snapshot($league, $type, '2030-01-01 12:00:00', ['item-a' => 4.0]);

        $this->assertEqualsCanonicalizing([2.0, 3.0], array_column($this->prices($league->id, 'divine')[$item->id], 'price'));
    }

    public function test_it_ignores_the_prices_of_other_leagues(): void
    {
        $league = $this->league('Bucket Own League');
        $other = $this->league('Bucket Other League');
        $type = $this->exchangeType();
        $this->item($league, 'item-a');
        $this->item($other, 'item-a');

        $this->snapshot($other, $type, '2030-01-01 01:00:00', ['item-a' => 9.0]);

        $this->assertSame([], $this->prices($league->id, 'divine'));
    }

    public function test_it_skips_cores_without_a_rate_for_the_currency(): void
    {
        $league = $this->league('Bucket Missing Currency League');
        $type = $this->exchangeType();
        $this->item($league, 'item-a');

        $this->snapshot($league, $type, '2030-01-01 01:00:00', ['item-a' => 1.0], ['divine' => 1.0]);

        $this->assertSame([], $this->prices($league->id, 'exalted'));
    }

    public function test_it_skips_non_positive_rates(): void
    {
        $league = $this->league('Bucket Zero Rate League');
        $type = $this->exchangeType();
        $this->item($league, 'item-a');
        $this->item($league, 'item-b');

        $this->snapshot($league, $type, '2030-01-01 01:00:00', ['item-a' => 0.0, 'item-b' => 1.0], ['divine' => 1.0, 'exalted' => 0.0]);

        $this->assertSame([], $this->prices($league->id, 'exalted'));
        $this->assertCount(1, $this->prices($league->id, 'divine'));
    }

    public function test_units_are_the_volume_divided_by_the_item_rate(): void
    {
        $league = $this->league('Bucket Units League');
        $type = $this->exchangeType();
        $item = $this->item($league, 'item-a');

        $this->snapshot(
            $league,
            $type,
            '2030-01-01 01:00:00',
            ['item-a' => 0.5],
            ['divine' => 1.0, 'exalted' => 600.0],
            ['item-a' => 10.0],
        );

        $this->assertSame([$item->id => [['price' => 0.5, 'units' => 20.0]]], $this->prices($league->id, 'divine'));
        $this->assertSame([$item->id => [['price' => 300.0, 'units' => 20.0]]], $this->prices($league->id, 'exalted'));
    }

    public function test_latest_prices_come_from_the_newest_check_in_the_requested_currency(): void
    {
        $league = $this->league('Bucket Latest League');
        $type = $this->exchangeType();
        $itemA = $this->item($league, 'item-a');
        $itemB = $this->item($league, 'item-b');

        $this->snapshot($league, $type, '2030-01-01 01:00:00', ['item-a' => 1.0, 'item-b' => 2.0]);
        $this->snapshot($league, $type, '2030-01-01 03:00:00', ['item-a' => 5.0], ['divine' => 1.0, 'exalted' => 600.0]);

        $latest = $this->latest($league->id, 'exalted');

        $this->assertSame('2030-01-01 03:00:00+00', $latest['snapshot_at']);
        $this->assertSame([$itemA->id => 3000.0], $latest['prices']);
        $this->assertArrayNotHasKey($itemB->id, $latest['prices']);
    }

    public function test_latest_prices_of_a_league_without_checks_are_empty(): void
    {
        $league = $this->league('Bucket Latest Empty League');

        $this->assertSame(['snapshot_at' => null, 'prices' => []], $this->latest($league->id, 'divine'));
    }

    /**
     * @return array{snapshot_at: string|null, prices: array<int, float>}
     */
    private function latest(int $leagueId, string $currencyTradeId): array
    {
        return $this->app->make(ExchangeBucketPriceRepositoryContract::class)
            ->getLatestPrices($leagueId, $currencyTradeId);
    }

    /**
     * @return array<int, list<array{price: float, units: float|null}>>
     */
    private function prices(int $leagueId, string $currencyTradeId): array
    {
        return $this->app->make(ExchangeBucketPriceRepositoryContract::class)->getPricesByItem(
            $leagueId,
            $currencyTradeId,
            CarbonImmutable::parse('2030-01-01 00:00:00', 'UTC'),
            CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'),
        );
    }
}
