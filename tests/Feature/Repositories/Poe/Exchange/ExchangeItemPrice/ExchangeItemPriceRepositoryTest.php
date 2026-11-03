<?php

namespace Tests\Feature\Repositories\Poe\Exchange\ExchangeItemPrice;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\Exchange\ExchangeItemPrice\ExchangeItemPriceRepositoryContract;
use Tests\FeatureTestCase;

class ExchangeItemPriceRepositoryTest extends FeatureTestCase
{
    public function test_insert_persists_every_row(): void
    {
        $league = $this->league('Prices Insert League');
        $core = $this->core($league);

        $divine = $this->item($league, 'divine', 'Divine Orb');
        $chaos = $this->item($league, 'chaos', 'Chaos Orb');

        $this->repository()->insert([
            ['rate' => 1.0, 'price_check_core_id' => $core->id, 'exchange_item_id' => $divine->id],
            ['rate' => 0.25, 'price_check_core_id' => $core->id, 'exchange_item_id' => $chaos->id],
        ]);

        $this->assertSame(2, ExchangeItemPrice::query()->where('price_check_core_id', $core->id)->count());
        $this->assertDatabaseHas('exchange_item_prices', [
            'rate' => 1.0,
            'price_check_core_id' => $core->id,
            'exchange_item_id' => $divine->id,
        ]);
        $this->assertDatabaseHas('exchange_item_prices', [
            'rate' => 0.25,
            'price_check_core_id' => $core->id,
            'exchange_item_id' => $chaos->id,
        ]);
    }

    public function test_insert_keeps_prices_of_different_cores_apart(): void
    {
        $league = $this->league('Prices Two Cores League');
        $divine = $this->item($league, 'divine', 'Divine Orb');

        $older = $this->core($league, '2030-01-01 10:00:00');
        $newer = $this->core($league, '2030-01-01 12:00:00');

        $this->repository()->insert([
            ['rate' => 10.0, 'price_check_core_id' => $older->id, 'exchange_item_id' => $divine->id],
            ['rate' => 12.0, 'price_check_core_id' => $newer->id, 'exchange_item_id' => $divine->id],
        ]);

        $this->assertSame(
            [10.0],
            ExchangeItemPrice::query()->where('price_check_core_id', $older->id)->pluck('rate')->all(),
        );
        $this->assertSame(
            [12.0],
            ExchangeItemPrice::query()->where('price_check_core_id', $newer->id)->pluck('rate')->all(),
        );
    }

    public function test_insert_with_no_rows_persists_nothing(): void
    {
        $before = ExchangeItemPrice::query()->count();

        $this->repository()->insert([]);

        $this->assertSame($before, ExchangeItemPrice::query()->count());
    }

    public function test_insert_appends_rows_instead_of_upserting(): void
    {
        $league = $this->league('Prices Duplicate League');
        $core = $this->core($league);
        $divine = $this->item($league, 'divine', 'Divine Orb');

        $row = ['rate' => 1.0, 'price_check_core_id' => $core->id, 'exchange_item_id' => $divine->id];

        $this->repository()->insert([$row]);
        $this->repository()->insert([$row]);

        $this->assertSame(
            2,
            ExchangeItemPrice::query()
                ->where('price_check_core_id', $core->id)
                ->where('exchange_item_id', $divine->id)
                ->count(),
        );
    }

    public function test_averages_rates_per_item_across_the_given_cores(): void
    {
        $league = $this->league('Prices Average League');
        $divine = $this->item($league, 'divine', 'Divine Orb');
        $chaos = $this->item($league, 'chaos', 'Chaos Orb');

        $older = $this->core($league, '2030-01-01 10:00:00');
        $newer = $this->core($league, '2030-01-01 12:00:00');

        $this->repository()->insert([
            ['rate' => 10.0, 'price_check_core_id' => $older->id, 'exchange_item_id' => $divine->id],
            ['rate' => 20.0, 'price_check_core_id' => $newer->id, 'exchange_item_id' => $divine->id],
            ['rate' => 4.0, 'price_check_core_id' => $older->id, 'exchange_item_id' => $chaos->id],
        ]);

        $result = $this->keyByTradeId($this->repository()->getAvgCorePricesData([$older->id, $newer->id]));

        $this->assertCount(2, $result);
        $this->assertSame('Divine Orb', $result['divine']['name']);
        $this->assertEqualsWithDelta(15.0, (float) $result['divine']['price_avg_rate'], 0.0001);
        $this->assertSame('Chaos Orb', $result['chaos']['name']);
        $this->assertEqualsWithDelta(4.0, (float) $result['chaos']['price_avg_rate'], 0.0001);
    }

    public function test_averages_only_the_given_cores(): void
    {
        $league = $this->league('Prices Scoped League');
        $divine = $this->item($league, 'divine', 'Divine Orb');
        $chaos = $this->item($league, 'chaos', 'Chaos Orb');

        $mine = $this->core($league, '2030-01-01 10:00:00');
        $other = $this->core($league, '2030-01-01 12:00:00');

        $this->repository()->insert([
            ['rate' => 10.0, 'price_check_core_id' => $mine->id, 'exchange_item_id' => $divine->id],
            ['rate' => 20.0, 'price_check_core_id' => $other->id, 'exchange_item_id' => $divine->id],
            ['rate' => 4.0, 'price_check_core_id' => $other->id, 'exchange_item_id' => $chaos->id],
        ]);

        $result = $this->repository()->getAvgCorePricesData([$mine->id]);

        $this->assertCount(1, $result);
        $this->assertSame('divine', $result[0]['trade_id']);
        $this->assertEqualsWithDelta(10.0, (float) $result[0]['price_avg_rate'], 0.0001);
    }

    public function test_averages_return_the_expected_keys(): void
    {
        $league = $this->league('Prices Keys League');
        $core = $this->core($league);
        $divine = $this->item($league, 'divine', 'Divine Orb');

        $this->repository()->insert([
            ['rate' => 1.0, 'price_check_core_id' => $core->id, 'exchange_item_id' => $divine->id],
        ]);

        $result = $this->repository()->getAvgCorePricesData([$core->id]);

        $this->assertEqualsCanonicalizing(
            ['price_avg_rate', 'name', 'trade_id'],
            array_keys($result[0]),
        );
    }

    public function test_averages_are_empty_without_prices(): void
    {
        $league = $this->league('Prices Empty League');
        $core = $this->core($league);

        $this->assertSame([], $this->repository()->getAvgCorePricesData([$core->id]));
        $this->assertSame([], $this->repository()->getAvgCorePricesData([]));
    }

    private function repository(): ExchangeItemPriceRepositoryContract
    {
        return $this->app->make(ExchangeItemPriceRepositoryContract::class);
    }

    private function league(string $name): PoeLeague
    {
        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => 'ninja-'.strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function item(PoeLeague $league, string $tradeId, string $name): ExchangeItem
    {
        return ExchangeItem::query()->create([
            'name' => $name,
            'trade_id' => $tradeId,
            'poe_league_id' => $league->id,
        ]);
    }

    private function core(PoeLeague $league, string $createdAt = '2030-01-01 10:00:00'): PriceCheckCore
    {
        return PriceCheckCore::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function keyByTradeId(array $rows): array
    {
        $keyed = [];

        foreach ($rows as $row) {
            $keyed[(string) $row['trade_id']] = $row;
        }

        return $keyed;
    }
}
