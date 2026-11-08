<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

final readonly class ItemSeriesRowDto
{
    public function __construct(
        public string $bucket_start,
        public float $price,
        public ?float $residual,
        public ?float $residual_mad,
    ) {}
}
