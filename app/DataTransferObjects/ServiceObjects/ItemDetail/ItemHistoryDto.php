<?php

namespace App\DataTransferObjects\ServiceObjects\ItemDetail;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemBetaDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemSeriesRowDto;
use App\Enums\MetricPeriodEnum;

final readonly class ItemHistoryDto
{
    /**
     * @param  ItemSeriesRowDto[]  $series
     * @param  ExchangeItemBetaDto[]  $betaHistory
     */
    public function __construct(
        public ?string $currency,
        public MetricPeriodEnum $period,
        public ?string $lastBucket,
        public array $series = [],
        public array $betaHistory = [],
    ) {}
}
