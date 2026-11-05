<?php

namespace App\Repositories\Poe\Exchange\Compact;

use App\Models\Poe\Exchange\Compact\CompactExchangeItemPrice;
use App\Models\Poe\Exchange\Compact\CompactPriceCheck;
use App\Models\Poe\Exchange\Compact\CompactPriceCheckCore;
use App\Models\Poe\Exchange\Compact\CompactPriceCheckCoreType;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeCompactRepository implements ExchangeCompactRepositoryContract
{
    /**
     * Returns the price checks created on or before the given UTC day,
     * together with the UTC day each of them belongs to.
     *
     * @return array<int, array{
     *     id: int,
     *     poe_league_id: int,
     *     date: string,
     * }>
     */
    #[\Override]
    public function getLeagueDailyPeriodsPriceCheckIds(Carbon $date): array
    {
        $dayExpression = "(price_checks.created_at AT TIME ZONE 'UTC')::date";

        return PriceCheck::query()
            ->select([
                'id',
                'poe_league_id',
                DB::raw("{$dayExpression} as date"),
            ])
            ->whereRaw("{$dayExpression} <= ?", [$date->copy()->setTimezone('UTC')->toDateString()])
            ->orderBy('id')
            ->get()
            ->toBase()
            ->map(static fn (PriceCheck $priceCheck): array => [
                'id' => $priceCheck->id,
                'poe_league_id' => $priceCheck->poe_league_id,
                'date' => (string) $priceCheck->getAttribute('date'),
            ])
            ->all();
    }

    /**
     * Averages the core types of the given price checks per exchange type and item.
     *
     * @param  array<int, int>  $priceCheckIds
     * @return array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_trade_id: string,
     *     name: string,
     *     rate: float,
     * }>
     */
    #[\Override]
    public function getAvgCoreTypesData(array $priceCheckIds): array
    {
        return PriceCheckCoreType::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'price_check_core_types.price_check_core_id')
            ->whereIn('price_check_cores.price_check_id', $priceCheckIds)
            ->groupBy([
                'price_check_cores.exchange_type_id',
                'price_check_core_types.exchange_item_trade_id',
            ])
            ->select([
                'price_check_cores.exchange_type_id',
                'price_check_core_types.exchange_item_trade_id',
                DB::raw('MAX(price_check_core_types.name) as name'),
                DB::raw('AVG(price_check_core_types.rate) as rate'),
            ])
            ->get()
            ->toBase()
            ->map(static fn (PriceCheckCoreType $type): array => [
                'exchange_type_id' => (int) $type->getAttribute('exchange_type_id'),
                'exchange_item_trade_id' => $type->exchange_item_trade_id,
                'name' => (string) $type->getAttribute('name'),
                'rate' => (float) $type->getAttribute('rate'),
            ])
            ->all();
    }

    /**
     * Averages the item prices of the given price checks per exchange type and item.
     *
     * @param  array<int, int>  $priceCheckIds
     * @return array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_id: int,
     *     rate: float,
     * }>
     */
    #[\Override]
    public function getAvgCorePricesData(array $priceCheckIds): array
    {
        return ExchangeItemPrice::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->whereIn('price_check_cores.price_check_id', $priceCheckIds)
            ->groupBy([
                'price_check_cores.exchange_type_id',
                'exchange_item_prices.exchange_item_id',
            ])
            ->select([
                'price_check_cores.exchange_type_id',
                'exchange_item_prices.exchange_item_id',
                DB::raw('AVG(exchange_item_prices.rate) as rate'),
            ])
            ->get()
            ->toBase()
            ->map(static fn (ExchangeItemPrice $price): array => [
                'exchange_type_id' => (int) $price->getAttribute('exchange_type_id'),
                'exchange_item_id' => $price->exchange_item_id,
                'rate' => (float) $price->getAttribute('rate'),
            ])
            ->all();
    }

    /**
     * Creates a compact price check for the league day in the compact tables:
     * one core per exchange type with the averaged types and prices.
     *
     * @param  array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_trade_id: string,
     *     name: string,
     *     rate: float,
     * }>  $types
     * @param  array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_id: int,
     *     rate: float,
     * }>  $prices
     *
     * @throws \InvalidArgumentException when the exchange type ids of $types and $prices differ
     */
    #[\Override]
    public function makeCompactPriceCheck(
        int $leagueId,
        string $date,
        array $types,
        array $prices,
    ): void {
        $typeExchangeTypeIds = collect($types)->pluck('exchange_type_id')->unique()->sort()->values()->all();
        $priceExchangeTypeIds = collect($prices)->pluck('exchange_type_id')->unique()->sort()->values()->all();

        if ($typeExchangeTypeIds !== $priceExchangeTypeIds) {
            throw new \InvalidArgumentException(
                'Exchange type ids of types ['.implode(', ', $typeExchangeTypeIds).'] '
                .'do not match exchange type ids of prices ['.implode(', ', $priceExchangeTypeIds).'].'
            );
        }

        DB::transaction(function () use ($leagueId, $date, $types, $prices) {
            $priceCheck = CompactPriceCheck::query()->create([
                'poe_league_id' => $leagueId,
                'date' => $date,
            ]);

            $typesByExchangeType = collect($types)->groupBy('exchange_type_id');
            $pricesByExchangeType = collect($prices)->groupBy('exchange_type_id');

            /** @var Collection<int, string> $exchangeTypeIds */
            $exchangeTypeIds = $typesByExchangeType->keys()
                ->merge($pricesByExchangeType->keys())
                ->unique();

            foreach ($exchangeTypeIds as $exchangeTypeId) {
                $core = CompactPriceCheckCore::query()->create([
                    'compact_price_check_id' => $priceCheck->id,
                    'exchange_type_id' => $exchangeTypeId,
                ]);

                /** @var Collection<int, array> $types */
                $types = $typesByExchangeType->get($exchangeTypeId);

                CompactPriceCheckCoreType::query()->insert(
                    $types
                        ->map(static fn (array $type): array => [
                            'rate' => $type['rate'],
                            'exchange_item_trade_id' => $type['exchange_item_trade_id'],
                            'name' => $type['name'],
                            'compact_price_check_core_id' => $core->id,
                        ])
                        ->all(),
                );

                /** @var Collection<int, array> $prices */
                $prices = $pricesByExchangeType->get($exchangeTypeId);

                CompactExchangeItemPrice::query()->insert(
                    $prices
                        ->map(static fn (array $price): array => [
                            'rate' => $price['rate'],
                            'exchange_item_id' => $price['exchange_item_id'],
                            'compact_price_check_core_id' => $core->id,
                        ])
                        ->all(),
                );
            }
        });
    }
}
