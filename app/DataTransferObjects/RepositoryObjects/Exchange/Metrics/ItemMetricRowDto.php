<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

final readonly class ItemMetricRowDto
{
    public function __construct(
        public string $bucket_start,
        public float $price,
        public ?float $log_return,
        public ?float $residual,
        public ?float $residual_mad,
        public int $observations,
        public ?float $units,
        public ?float $return_weight,
    ) {}
}
