<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\ExchangeMetricsMixResultDto;
use App\DataTransferObjects\ServiceObjects\ExchangeMetricsSeedResultDto;
use App\Enums\ExchangeMetricsMixStatusEnum;
use App\Enums\ExchangeMetricsSeedStatusEnum;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Utility\Statistics\RobustStatistics;

final readonly class ExchangeMetricsItemSeedService implements ExchangeMetricsItemSeedServiceContract
{
    private const float TOP_PERCENTILE = 0.8;

    public function __construct(
        private ExchangeMetricsItemRepositoryContract $metricsItemRepository,
        private ExchangeItemRepositoryContract $exchangeItemRepository,
        private PriceCheckRepositoryContract $priceCheckRepository,
        private RobustStatistics $statistics,
    ) {}

    #[\Override]
    public function seedForLeague(int $leagueId): ExchangeMetricsSeedResultDto
    {
        if ($this->metricsItemRepository->existsForLeague($leagueId)) {
            return new ExchangeMetricsSeedResultDto(ExchangeMetricsSeedStatusEnum::AlreadySeeded);
        }

        $priceCheckId = $this->priceCheckRepository->findLatestIdByLeague($leagueId);

        if ($priceCheckId === null) {
            return new ExchangeMetricsSeedResultDto(ExchangeMetricsSeedStatusEnum::NoPriceCheck);
        }

        $rows = [];
        $items = [];

        foreach ($this->priceCheckRepository->getPricesByCurrency($priceCheckId) as $currencyItemId => $currency) {
            $minPrice = $this->statistics->percentile($currency['prices'], self::TOP_PERCENTILE);

            $rows[] = [
                'poe_league_id' => $leagueId,
                'exchange_item_id' => $currencyItemId,
                'min_price' => $minPrice,
            ];

            $items[] = [
                'name' => $currency['name'],
                'min_price' => $minPrice,
                'sample_size' => count($currency['prices']),
            ];
        }

        if ($rows === []) {
            return new ExchangeMetricsSeedResultDto(ExchangeMetricsSeedStatusEnum::NoPricedItems);
        }

        $this->metricsItemRepository->insertMany($rows);

        return new ExchangeMetricsSeedResultDto(ExchangeMetricsSeedStatusEnum::Seeded, $items);
    }

    #[\Override]
    public function mixItemForLeague(int $leagueId, string $tradeId): ExchangeMetricsMixResultDto
    {
        $item = $this->exchangeItemRepository->findByTradeId($leagueId, $tradeId);

        if ($item === null) {
            return new ExchangeMetricsMixResultDto(ExchangeMetricsMixStatusEnum::ItemNotFound);
        }

        if ($this->metricsItemRepository->existsForLeagueItem($leagueId, $item->id)) {
            return new ExchangeMetricsMixResultDto(ExchangeMetricsMixStatusEnum::AlreadyExists, $item->name);
        }

        $priceCheckId = $this->priceCheckRepository->findLatestIdByLeague($leagueId);

        if ($priceCheckId === null) {
            return new ExchangeMetricsMixResultDto(ExchangeMetricsMixStatusEnum::NoPriceCheck, $item->name);
        }

        $price = $this->priceCheckRepository->findItemPrimaryPrice($priceCheckId, $tradeId);

        if ($price === null || $price['rate'] <= 0) {
            return new ExchangeMetricsMixResultDto(ExchangeMetricsMixStatusEnum::NoItemPrice, $item->name);
        }

        $currencyRate = 1.0 / $price['rate'];
        $prices = array_map(
            static fn (float $rate): float => $rate * $currencyRate,
            $this->priceCheckRepository->getItemRates($priceCheckId, $item->id),
        );

        if ($prices === []) {
            return new ExchangeMetricsMixResultDto(ExchangeMetricsMixStatusEnum::NoPricedItems, $item->name);
        }

        $minPrice = $this->statistics->percentile($prices, self::TOP_PERCENTILE);

        $this->metricsItemRepository->insertMany([[
            'poe_league_id' => $leagueId,
            'exchange_item_id' => $item->id,
            'min_price' => $minPrice,
        ]]);

        return new ExchangeMetricsMixResultDto(
            ExchangeMetricsMixStatusEnum::Added,
            $item->name,
            $minPrice,
            count($prices),
        );
    }
}
