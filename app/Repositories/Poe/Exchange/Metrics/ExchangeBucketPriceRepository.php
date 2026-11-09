<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheck;
use App\Utility\Statistics\RobustStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeBucketPriceRepository implements ExchangeBucketPriceRepositoryContract
{
    public function __construct(
        private RobustStatistics $statistics,
    ) {}

    /**
     * The stored rate is expressed in the core's primary currency, so it is
     * multiplied by the core's rate of the requested currency. Units are the items
     * traded per hour (volume divided by the item rate), null when the volume is unknown.
     *
     * @return array<int, list<array{price: float, units: float|null}>>
     */
    #[\Override]
    public function getPricesByItem(
        int $leagueId,
        string $currencyTradeId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $prices = [];

        $rows = ExchangeItemPrice::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->join('price_checks', 'price_checks.id', '=', 'price_check_cores.price_check_id')
            ->join('price_check_core_types', static function (JoinClause $join) use ($currencyTradeId): void {
                $join->on('price_check_core_types.price_check_core_id', '=', 'price_check_cores.id')
                    ->where('price_check_core_types.exchange_item_trade_id', '=', $currencyTradeId);
            })
            ->where('price_checks.poe_league_id', $leagueId)
            ->where('price_checks.created_at', '>=', $from->utc()->format('Y-m-d H:i:sP'))
            ->where('price_checks.created_at', '<', $to->utc()->format('Y-m-d H:i:sP'))
            ->where('exchange_item_prices.rate', '>', 0)
            ->where('price_check_core_types.rate', '>', 0)
            ->select([
                'exchange_item_prices.exchange_item_id',
                DB::raw('exchange_item_prices.rate * price_check_core_types.rate as price'),
                DB::raw('exchange_item_prices.volume / exchange_item_prices.rate as units'),
            ])
            ->cursor();

        foreach ($rows as $row) {
            $units = $row->getAttribute('units');

            $prices[$row->exchange_item_id][] = [
                'price' => (float) $row->getAttribute('price'),
                'units' => $units === null ? null : (float) $units,
            ];
        }

        return $prices;
    }

    /**
     * @return array{snapshot_at: string|null, prices: array<int, float>}
     */
    #[\Override]
    public function getLatestPrices(int $leagueId, string $currencyTradeId): array
    {
        $latest = PriceCheck::query()
            ->where('poe_league_id', $leagueId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->toBase()
            ->first(['id', 'created_at']);

        if ($latest === null) {
            return ['snapshot_at' => null, 'prices' => []];
        }

        $rows = ExchangeItemPrice::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->join('price_check_core_types', static function (JoinClause $join) use ($currencyTradeId): void {
                $join->on('price_check_core_types.price_check_core_id', '=', 'price_check_cores.id')
                    ->where('price_check_core_types.exchange_item_trade_id', '=', $currencyTradeId);
            })
            ->where('price_check_cores.price_check_id', (int) $latest->id)
            ->where('exchange_item_prices.rate', '>', 0)
            ->where('price_check_core_types.rate', '>', 0)
            ->select([
                'exchange_item_prices.exchange_item_id',
                DB::raw('exchange_item_prices.rate * price_check_core_types.rate as price'),
            ])
            ->toBase()
            ->get();

        $samples = [];

        foreach ($rows as $row) {
            $samples[(int) $row->exchange_item_id][] = (float) $row->price;
        }

        // A currency is priced by every core of the check, so the item price is their median,
        // the same way a bucket summarizes its snapshots.
        $prices = [];

        foreach ($samples as $itemId => $values) {
            $prices[$itemId] = $this->statistics->median($values);
        }

        return ['snapshot_at' => (string) $latest->created_at, 'prices' => $prices];
    }
}
