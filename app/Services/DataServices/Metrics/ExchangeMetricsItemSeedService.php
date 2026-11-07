<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\ExchangeMetricsSeedResultDto;
use App\Enums\ExchangeMetricsSeedStatusEnum;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Utility\Statistics\RobustStatistics;

final readonly class ExchangeMetricsItemSeedService implements ExchangeMetricsItemSeedServiceContract
{
    private const float TOP_SHARE = 0.2;

    public function __construct(
        private ExchangeMetricsItemRepositoryContract $metricsItemRepository,
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
            $minPrice = $this->statistics->percentile($currency['prices'], 1.0 - self::TOP_SHARE);

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
}
