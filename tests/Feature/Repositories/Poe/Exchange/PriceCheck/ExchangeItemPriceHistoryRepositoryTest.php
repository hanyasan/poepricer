<?php

namespace Tests\Feature\Repositories\Poe\Exchange\PriceCheck;

use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Repositories\Poe\Exchange\PriceCheck\ExchangeItemPriceHistoryRepositoryContract;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class ExchangeItemPriceHistoryRepositoryTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private const string FROM = '2030-01-10 00:00:00';

    private const string TO = '2030-01-10 12:00:00';

    public function test_currencies_come_from_the_cores_of_the_item_without_duplicates_most_covered_first(): void
    {
        $league = $this->league('History Currencies League');
        $item = $this->item($league, 'exalted');
        $type = $this->exchangeType('type-history-currencies');

        $this->snapshot($league, $type, '2030-01-10 00:00:00', ['exalted' => 0.5], ['divine' => 1.0, 'chaos' => 100.0]);
        $this->snapshot($league, $type, '2030-01-10 06:00:00', ['exalted' => 0.5], ['divine' => 1.0, 'chaos' => 100.0, 'mirror' => 0.001]);
        $this->snapshot($league, $type, '2030-01-10 08:00:00', ['exalted' => 0.5], ['divine' => 1.0]);
        $this->snapshot($league, $type, '2030-01-10 09:00:00', [], ['unrelated' => 1.0]);

        $currencies = $this->repository()->getCurrencies($league->id, $item->id, 'exalted', $this->windowStart(), $this->windowEnd());

        $this->assertSame(
            [['divine', 3], ['chaos', 2], ['mirror', 1]],
            array_map(static fn ($row): array => [$row->slug, $row->points], $currencies),
        );
        $this->assertSame('DIVINE', $currencies[0]->name);
    }

    public function test_currencies_skip_the_item_itself_and_zero_rates(): void
    {
        $league = $this->league('History Zero Rates League');
        $item = $this->item($league, 'chaos');
        $type = $this->exchangeType('type-history-zero');

        $this->snapshot($league, $type, '2030-01-10 06:00:00', ['chaos' => 0.01], ['divine' => 1.0, 'chaos' => 100.0, 'mirror' => 0.0]);

        $currencies = $this->repository()->getCurrencies($league->id, $item->id, 'chaos', $this->windowStart(), $this->windowEnd());

        $this->assertSame(['divine'], array_map(static fn ($row): string => $row->slug, $currencies));
    }

    public function test_points_are_bounded_inclusively_and_ordered_by_time(): void
    {
        $league = $this->league('History Points League');
        $item = $this->item($league, 'exalted');
        $type = $this->exchangeType('type-history-points');

        $this->snapshot($league, $type, '2030-01-10 12:00:00', ['exalted' => 0.3]);
        $this->snapshot($league, $type, '2030-01-09 23:59:59', ['exalted' => 0.1]);
        $this->snapshot($league, $type, '2030-01-10 00:00:00', ['exalted' => 0.2], ['divine' => 1.0], ['exalted' => 10.0]);
        $this->snapshot($league, $type, '2030-01-10 12:00:01', ['exalted' => 0.4]);

        $points = $this->repository()->getPoints($league->id, $item->id, 'divine', $this->windowStart(), $this->windowEnd());

        $this->assertCount(2, $points);
        $this->assertEqualsWithDelta([0.2, 0.3], array_map(static fn ($row): float => $row->price, $points), 1e-9);
        $this->assertEqualsWithDelta([50.0, 0.0], array_map(static fn ($row): float => $row->units, $points), 1e-9);
    }

    public function test_an_item_in_several_cores_of_a_price_check_gives_one_point_from_the_first_price(): void
    {
        $league = $this->league('History Duplicates League');
        $item = $this->item($league, 'exalted');
        $type = $this->exchangeType('type-history-duplicates');

        $core = $this->snapshot($league, $type, '2030-01-10 06:00:00', ['exalted' => 0.5]);
        $second = PriceCheckCore::query()->create([
            'price_check_id' => $core->price_check_id,
            'exchange_type_id' => $this->exchangeType('type-history-duplicates-second')->id,
        ]);
        PriceCheckCoreType::query()->create([
            'rate' => 1.0,
            'exchange_item_trade_id' => 'divine',
            'name' => 'DIVINE',
            'price_check_core_id' => $second->id,
        ]);
        ExchangeItemPrice::query()->create([
            'rate' => 0.9,
            'exchange_item_id' => $item->id,
            'price_check_core_id' => $second->id,
        ]);

        $points = $this->repository()->getPoints($league->id, $item->id, 'divine', $this->windowStart(), $this->windowEnd());

        $this->assertCount(1, $points);
        $this->assertEqualsWithDelta(0.5, $points[0]->price, 1e-9);
    }

    public function test_a_price_check_without_the_currency_gives_no_point(): void
    {
        $league = $this->league('History Missing Currency League');
        $item = $this->item($league, 'exalted');
        $type = $this->exchangeType('type-history-missing');

        $this->snapshot($league, $type, '2030-01-10 06:00:00', ['exalted' => 0.5], ['divine' => 1.0, 'chaos' => 100.0]);
        $this->snapshot($league, $type, '2030-01-10 07:00:00', ['exalted' => 0.5], ['divine' => 1.0]);

        $this->assertCount(1, $this->repository()->getPoints($league->id, $item->id, 'chaos', $this->windowStart(), $this->windowEnd()));
        $this->assertCount(2, $this->repository()->getPoints($league->id, $item->id, 'divine', $this->windowStart(), $this->windowEnd()));
    }

    private function repository(): ExchangeItemPriceHistoryRepositoryContract
    {
        return $this->app->make(ExchangeItemPriceHistoryRepositoryContract::class);
    }

    private function windowStart(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::FROM, 'UTC');
    }

    private function windowEnd(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::TO, 'UTC');
    }
}
