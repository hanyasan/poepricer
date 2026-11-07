<?php

namespace Tests\Feature\Services\DataServices\Metrics;

use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Models\Poe\PoeLeague;
use App\Services\DataServices\Metrics\PriceCheckCurrencyMixServiceContract;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class PriceCheckCurrencyMixServiceTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private PoeLeague $league;

    private ExchangeType $currencyType;

    private ExchangeType $gemType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->league = $this->league('Currency Mix League');
        $this->currencyType = $this->exchangeType('type-currency');
        $this->gemType = $this->exchangeType('type-gems');
    }

    public function test_it_mixes_the_currency_into_every_core_as_the_reciprocal_of_its_price(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'mirror'));
        $currencyCore = $this->snapshotWith($this->currencyType, ['mirror' => 4000.0]);
        $gemCore = $this->coreOfSamePriceCheck($currencyCore, $this->gemType);

        $this->mix($currencyCore);

        foreach ([$currencyCore, $gemCore] as $core) {
            $type = PriceCheckCoreType::query()
                ->where('price_check_core_id', $core->id)
                ->where('exchange_item_trade_id', 'mirror')
                ->sole();

            $this->assertSame(1 / 4000.0, $type->rate);
            $this->assertSame('MIRROR', $type->name);
        }
    }

    public function test_it_skips_a_currency_that_every_core_already_has(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'divine'));
        $core = $this->snapshotWith($this->currencyType, ['divine' => 1.0], ['divine' => 1.0]);

        $this->mix($core);

        $this->assertSame(1, $this->coreTypeCount('divine'));
    }

    public function test_it_fills_only_the_cores_that_lack_the_currency(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'mirror'));
        $currencyCore = $this->snapshotWith($this->currencyType, ['mirror' => 5000.0], ['divine' => 1.0, 'mirror' => 0.5]);
        $gemCore = $this->coreOfSamePriceCheck($currencyCore, $this->gemType);

        $this->mix($currencyCore);

        $this->assertSame(
            [0.5],
            PriceCheckCoreType::query()
                ->where('price_check_core_id', $currencyCore->id)
                ->where('exchange_item_trade_id', 'mirror')
                ->pluck('rate')
                ->all(),
        );
        $this->assertSame(
            [1 / 5000.0],
            PriceCheckCoreType::query()
                ->where('price_check_core_id', $gemCore->id)
                ->where('exchange_item_trade_id', 'mirror')
                ->pluck('rate')
                ->all(),
        );
    }

    public function test_it_mixes_nothing_when_the_currency_has_no_price_in_the_price_check(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'mirror'));
        $core = $this->snapshotWith($this->currencyType, []);

        $this->mix($core);

        $this->assertSame(0, $this->coreTypeCount('mirror'));
    }

    public function test_it_mixes_nothing_for_a_currency_with_a_zero_price(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'mirror'));
        $core = $this->snapshotWith($this->currencyType, ['mirror' => 0.0]);

        $this->mix($core);

        $this->assertSame(0, $this->coreTypeCount('mirror'));
    }

    public function test_it_mixes_nothing_when_the_league_has_no_metrics_items(): void
    {
        $this->item($this->league, 'mirror');
        $core = $this->snapshotWith($this->currencyType, ['mirror' => 4000.0]);

        $this->mix($core);

        $this->assertSame(0, $this->coreTypeCount('mirror'));
    }

    public function test_it_ignores_the_metrics_items_of_other_leagues(): void
    {
        $other = $this->league('Other Mix League');
        $this->metricsItem($other, $this->item($other, 'mirror'));
        $this->item($this->league, 'mirror');
        $core = $this->snapshotWith($this->currencyType, ['mirror' => 4000.0]);

        $this->mix($core);

        $this->assertSame(0, $this->coreTypeCount('mirror'));
    }

    public function test_mixing_twice_does_not_duplicate_the_core_types(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'mirror'));
        $core = $this->snapshotWith($this->currencyType, ['mirror' => 4000.0]);

        $this->mix($core);
        $this->mix($core);

        $this->assertSame(1, $this->coreTypeCount('mirror'));
    }

    public function test_it_mixes_every_currency_of_the_metrics_items(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'mirror'));
        $this->metricsItem($this->league, $this->item($this->league, 'lock'));
        $core = $this->snapshotWith($this->currencyType, ['mirror' => 4000.0, 'lock' => 1000.0]);

        $this->mix($core);

        $this->assertSame(
            ['lock' => 1 / 1000.0, 'mirror' => 1 / 4000.0],
            PriceCheckCoreType::query()
                ->where('price_check_core_id', $core->id)
                ->whereIn('exchange_item_trade_id', ['mirror', 'lock'])
                ->orderBy('exchange_item_trade_id')
                ->pluck('rate', 'exchange_item_trade_id')
                ->all(),
        );
    }

    public function test_it_does_not_touch_other_price_checks(): void
    {
        $this->metricsItem($this->league, $this->item($this->league, 'mirror'));
        $core = $this->snapshotWith($this->currencyType, ['mirror' => 4000.0]);
        $untouched = $this->snapshotWith($this->currencyType, ['mirror' => 5000.0]);

        $this->mix($core);

        $this->assertSame(
            0,
            PriceCheckCoreType::query()->where('price_check_core_id', $untouched->id)->count(),
        );
    }

    /**
     * @param  array<string, float>  $rates
     * @param  array<string, float>  $coreRates
     */
    private function snapshotWith(ExchangeType $type, array $rates, array $coreRates = []): PriceCheckCore
    {
        return $this->snapshot($this->league, $type, '2030-01-01 01:00:00', $rates, $coreRates);
    }

    private function coreOfSamePriceCheck(PriceCheckCore $core, ExchangeType $type): PriceCheckCore
    {
        return PriceCheckCore::query()->create([
            'price_check_id' => $core->price_check_id,
            'exchange_type_id' => $type->id,
        ]);
    }

    private function coreTypeCount(string $tradeId): int
    {
        return PriceCheckCoreType::query()
            ->where('exchange_item_trade_id', $tradeId)
            ->whereIn('price_check_core_id', PriceCheckCore::query()
                ->whereIn('price_check_id', PriceCheck::query()
                    ->where('poe_league_id', $this->league->id)
                    ->select('id'))
                ->select('id'))
            ->count();
    }

    private function mix(PriceCheckCore $core): void
    {
        $this->app->make(PriceCheckCurrencyMixServiceContract::class)
            ->mixInCurrencies($core->price_check_id, $this->league->id);
    }
}
