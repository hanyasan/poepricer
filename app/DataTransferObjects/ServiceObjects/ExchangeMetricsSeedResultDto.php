<?php

namespace App\DataTransferObjects\ServiceObjects;

use App\Enums\ExchangeMetricsSeedStatusEnum;

final readonly class ExchangeMetricsSeedResultDto
{
    /**
     * @param  list<array{name: string, min_price: float, sample_size: int}>  $items
     */
    public function __construct(
        public ExchangeMetricsSeedStatusEnum $status,
        public array $items = [],
    ) {}
}
