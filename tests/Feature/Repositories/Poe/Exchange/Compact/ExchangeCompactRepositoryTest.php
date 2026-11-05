<?php

namespace Tests\Feature\Repositories\Poe\Exchange\Compact;

use App\Models\Poe\Exchange\Compact\CompactPriceCheck;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\Exchange\Compact\ExchangeCompactRepository;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\FeatureTestCase;

class ExchangeCompactRepositoryTest extends FeatureTestCase
{
    public function test_get_league_daily_periods_returns_price_checks_up_to_the_date(): void
    {
        $league = $this->league('Daily Periods League');

        $old = $this->priceCheck($league, '2030-01-01 23:30:00');
        $border = $this->priceCheck($league, '2030-01-02 00:10:00');
        $fresh = $this->priceCheck($league, '2030-01-03 00:10:00');

        $result = collect($this->repository()->getLeagueDailyPeriodsPriceCheckIds(Carbon::parse('2030-01-02', 'UTC')))
            ->keyBy('id');

        $this->assertSame('2030-01-01', $result[$old->id]['date']);
        $this->assertSame('2030-01-02', $result[$border->id]['date']);
        $this->assertSame($league->id, $result[$old->id]['poe_league_id']);
        $this->assertFalse($result->has($fresh->id));
    }

    public function test_get_avg_core_types_data_averages_rates_per_type_and_item(): void
    {
        $league = $this->league('Avg Types League');
        $type = $this->exchangeType('avg-types');
        $other = PriceCheck::query()->create(['poe_league_id' => $league->id, 'created_at' => now()]);

        $first = $this->core($this->priceCheck($league), $type);
        $second = $this->core($this->priceCheck($league), $type);
        $ignored = $this->core($other, $type);

        foreach ([[$first, 2.0], [$second, 4.0], [$ignored, 100.0]] as [$core, $rate]) {
            PriceCheckCoreType::query()->create([
                'rate' => $rate,
                'exchange_item_trade_id' => 'divine',
                'name' => 'Divine Orb',
                'price_check_core_id' => $core->id,
            ]);
        }

        $result = $this->repository()->getAvgCoreTypesData([
            $first->price_check_id,
            $second->price_check_id,
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($type->id, $result[0]['exchange_type_id']);
        $this->assertSame('divine', $result[0]['exchange_item_trade_id']);
        $this->assertSame('Divine Orb', $result[0]['name']);
        $this->assertEqualsWithDelta(3.0, $result[0]['rate'], 0.0001);
    }

    public function test_get_avg_core_prices_data_averages_rates_per_type_and_item(): void
    {
        $league = $this->league('Avg Prices League');
        $type = $this->exchangeType('avg-prices');
        $item = $this->item($league, 'chaos', 'Chaos Orb');

        $first = $this->core($this->priceCheck($league), $type);
        $second = $this->core($this->priceCheck($league), $type);

        foreach ([[$first, 10.0], [$second, 20.0]] as [$core, $rate]) {
            ExchangeItemPrice::query()->create([
                'rate' => $rate,
                'exchange_item_id' => $item->id,
                'price_check_core_id' => $core->id,
            ]);
        }

        $result = $this->repository()->getAvgCorePricesData([
            $first->price_check_id,
            $second->price_check_id,
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($type->id, $result[0]['exchange_type_id']);
        $this->assertSame($item->id, $result[0]['exchange_item_id']);
        $this->assertEqualsWithDelta(15.0, $result[0]['rate'], 0.0001);
    }

    public function test_make_compact_price_check_persists_cores_types_and_prices(): void
    {
        $league = $this->league('Make Compact League');
        $typeA = $this->exchangeType('compact-a');
        $typeB = $this->exchangeType('compact-b');
        $item = $this->item($league, 'exalted', 'Exalted Orb');

        $this->repository()->makeCompactPriceCheck(
            $league->id,
            '2030-01-02 23:30:00',
            [
                ['exchange_type_id' => $typeA->id, 'exchange_item_trade_id' => 'divine', 'name' => 'Divine Orb', 'rate' => 3.5],
                ['exchange_type_id' => $typeB->id, 'exchange_item_trade_id' => 'chaos', 'name' => 'Chaos Orb', 'rate' => 1.0],
            ],
            [
                ['exchange_type_id' => $typeA->id, 'exchange_item_id' => $item->id, 'rate' => 12.5],
                ['exchange_type_id' => $typeB->id, 'exchange_item_id' => $item->id, 'rate' => 2.5],
            ],
        );

        $compact = CompactPriceCheck::query()->where('poe_league_id', $league->id)->firstOrFail();

        $this->assertSame('2030-01-02', Carbon::parse($compact->date)->toDateString());
        $this->assertSame(2, $compact->cores()->count());

        $coreA = $compact->cores()->where('exchange_type_id', $typeA->id)->firstOrFail();

        $this->assertDatabaseHas('compact_price_check_core_types', [
            'compact_price_check_core_id' => $coreA->id,
            'exchange_item_trade_id' => 'divine',
            'name' => 'Divine Orb',
            'rate' => 3.5,
        ]);
        $this->assertDatabaseHas('compact_exchange_item_prices', [
            'compact_price_check_core_id' => $coreA->id,
            'exchange_item_id' => $item->id,
            'rate' => 12.5,
        ]);
        $this->assertDatabaseCount('compact_exchange_item_prices', 2);
    }

    public function test_make_compact_price_check_throws_when_exchange_type_ids_differ(): void
    {
        $league = $this->league('Compact Mismatch League');
        $typeA = $this->exchangeType('mismatch-a');
        $typeB = $this->exchangeType('mismatch-b');
        $item = $this->item($league, 'chaos', 'Chaos Orb');

        try {
            $this->repository()->makeCompactPriceCheck(
                $league->id,
                '2030-01-02',
                [['exchange_type_id' => $typeA->id, 'exchange_item_trade_id' => 'chaos', 'name' => 'Chaos Orb', 'rate' => 1.0]],
                [['exchange_type_id' => $typeB->id, 'exchange_item_id' => $item->id, 'rate' => 1.0]],
            );

            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseMissing('compact_price_checks', ['poe_league_id' => $league->id]);
        }
    }

    private function repository(): ExchangeCompactRepository
    {
        return $this->app->make(ExchangeCompactRepository::class);
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

    private function exchangeType(string $value): ExchangeType
    {
        return ExchangeType::query()->create([
            'category' => $value,
            'value' => $value,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function priceCheck(PoeLeague $league, string $createdAt = '2030-01-01 10:00:00'): PriceCheck
    {
        return PriceCheck::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);
    }

    private function core(PriceCheck $priceCheck, ExchangeType $exchangeType): PriceCheckCore
    {
        return PriceCheckCore::query()->create([
            'price_check_id' => $priceCheck->id,
            'exchange_type_id' => $exchangeType->id,
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
}
