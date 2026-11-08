<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

final readonly class ItemPricePointDto
{
    public function __construct(
        public string $bucket_start,
        public float $price,
    ) {}
}
