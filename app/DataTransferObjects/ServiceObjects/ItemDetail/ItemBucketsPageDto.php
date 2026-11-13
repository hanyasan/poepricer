<?php

namespace App\DataTransferObjects\ServiceObjects\ItemDetail;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemMetricRowDto;
use App\DataTransferObjects\ServiceObjects\PaginatedMetaDto;
use App\Enums\MetricPeriodEnum;

final readonly class ItemBucketsPageDto
{
    /**
     * @param  ItemMetricRowDto[]  $items
     */
    public function __construct(
        public array $items,
        public PaginatedMetaDto $meta,
        public ?string $currency,
        public MetricPeriodEnum $period,
    ) {}
}
