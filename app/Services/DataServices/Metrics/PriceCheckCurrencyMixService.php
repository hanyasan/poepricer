<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;

final readonly class PriceCheckCurrencyMixService implements PriceCheckCurrencyMixServiceContract
{
    public function __construct(
        private ExchangeMetricsItemRepositoryContract $metricsItemRepository,
        private PriceCheckRepositoryContract $priceCheckRepository,
    ) {}

    /**
     * The rate of a currency is the amount of it that one unit of the primary currency
     * buys, so it is the reciprocal of the currency price. Every core of a price check
     * shares the primary currency, so one rate is used for all of its cores.
     */
    #[\Override]
    public function mixInCurrencies(int $priceCheckId, int $leagueId): void
    {
        $tradeIds = array_unique(array_map(
            static fn (ExchangeMetricsItemDto $metricsItem): string => $metricsItem->exchange_item->trade_id,
            $this->metricsItemRepository->getAllByLeague($leagueId),
        ));

        foreach ($tradeIds as $tradeId) {
            $this->mixInCurrency($priceCheckId, $tradeId);
        }
    }

    private function mixInCurrency(int $priceCheckId, string $tradeId): void
    {
        $coreIds = $this->priceCheckRepository->getCoreIdsWithoutCoreType($priceCheckId, $tradeId);

        if ($coreIds === []) {
            return;
        }

        $price = $this->priceCheckRepository->findItemPrimaryPrice($priceCheckId, $tradeId);

        if ($price === null || $price['rate'] <= 0) {
            return;
        }

        $this->priceCheckRepository->insertCoreTypes(array_map(
            static fn (int $coreId): array => [
                'rate' => 1.0 / $price['rate'],
                'exchange_item_trade_id' => $tradeId,
                'name' => $price['name'],
                'price_check_core_id' => $coreId,
            ],
            $coreIds,
        ));
    }
}
