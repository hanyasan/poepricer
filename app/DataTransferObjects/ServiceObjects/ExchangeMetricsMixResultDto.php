<?php

namespace App\DataTransferObjects\ServiceObjects;

use App\Enums\ExchangeMetricsMixStatusEnum;

final readonly class ExchangeMetricsMixResultDto
{
    public function __construct(
        public ExchangeMetricsMixStatusEnum $status,
        public ?string $name = null,
        public ?float $min_price = null,
        public int $sample_size = 0,
    ) {}
}
