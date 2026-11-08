<?php

namespace Tests\Concerns;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;

trait BuildsExchangeMetricsData
{
    private function league(string $name): PoeLeague
    {
        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => 'ninja-'.strtolower(str_replace(' ', '-', $name)),
            'slug' => 'poe2-ninja-'.strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function exchangeType(string $value = 'type-alpha'): ExchangeType
    {
        return ExchangeType::query()->create([
            'category' => $value,
            'value' => $value,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function item(PoeLeague $league, string $tradeId): ExchangeItem
    {
        return ExchangeItem::query()->create([
            'name' => strtoupper($tradeId),
            'trade_id' => $tradeId,
            'poe_league_id' => $league->id,
        ]);
    }

    private function metricsItem(PoeLeague $league, ExchangeItem $currency, float $minPrice = 0.0): ExchangeMetricsItem
    {
        return ExchangeMetricsItem::factory()
            ->forLeagueCurrency($league, $currency)
            ->withMinPrice($minPrice)
            ->create();
    }

    /**
     * Stores one price check with a single core.
     *
     * @param  array<string, float>  $rates  stored item rate by item trade id
     * @param  array<string, float>  $coreRates  rate of each currency in the core, the primary one has 1
     * @param  array<string, float>  $volumes  traded volume in the primary currency by item trade id
     */
    private function snapshot(
        PoeLeague $league,
        ExchangeType $type,
        string $createdAt,
        array $rates,
        array $coreRates = ['divine' => 1.0],
        array $volumes = [],
    ): PriceCheckCore {
        $priceCheck = PriceCheck::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);

        $core = PriceCheckCore::query()->create([
            'price_check_id' => $priceCheck->id,
            'exchange_type_id' => $type->id,
        ]);

        foreach ($coreRates as $tradeId => $rate) {
            PriceCheckCoreType::query()->create([
                'rate' => $rate,
                'exchange_item_trade_id' => $tradeId,
                'name' => strtoupper($tradeId),
                'price_check_core_id' => $core->id,
            ]);
        }

        foreach ($rates as $tradeId => $rate) {
            ExchangeItemPrice::query()->create([
                'rate' => $rate,
                'exchange_item_id' => (int) ExchangeItem::query()
                    ->where('poe_league_id', $league->id)
                    ->where('trade_id', $tradeId)
                    ->value('id'),
                'price_check_core_id' => $core->id,
                'volume' => $volumes[$tradeId] ?? null,
            ]);
        }

        return $core;
    }
}
