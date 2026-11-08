<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;

final readonly class ExchangeItemMetricDto
{
    public function __construct(
        public int $id,
        public string $bucket_start,
        public float $price,
        public ?float $log_return,
        public ?float $residual,
        public ?float $residual_mad,
        public int $observations,
        public ?float $units,
        public ?float $return_weight,
        public int $exchange_item_id,
        public int $exchange_metrics_item_id,
        public ExchangeItemDto $exchange_item,
    ) {}
}
