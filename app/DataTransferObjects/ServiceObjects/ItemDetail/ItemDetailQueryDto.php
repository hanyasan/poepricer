<?php

namespace App\DataTransferObjects\ServiceObjects\ItemDetail;

use App\Enums\MetricPeriodEnum;

final readonly class ItemDetailQueryDto
{
    public function __construct(
        public ?string $currency,
        public MetricPeriodEnum $period,
    ) {}
}
