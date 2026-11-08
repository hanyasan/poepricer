<?php

namespace App\DataTransferObjects\ServiceObjects\ItemDetail;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemMetricRowDto;
use App\Enums\MetricPeriodEnum;

final readonly class ItemBucketsPageDto
{
    /**
     * @param  ItemMetricRowDto[]  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public ?string $currency,
        public MetricPeriodEnum $period,
    ) {}
}
