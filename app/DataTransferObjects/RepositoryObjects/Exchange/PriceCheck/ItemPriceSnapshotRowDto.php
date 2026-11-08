<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck;

final readonly class ItemPriceSnapshotRowDto
{
    public function __construct(
        public string $at,
        public float $price,
        public float $units,
    ) {}
}
