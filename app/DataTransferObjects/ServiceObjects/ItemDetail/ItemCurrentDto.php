<?php

namespace App\DataTransferObjects\ServiceObjects\ItemDetail;

final readonly class ItemCurrentDto
{
    public function __construct(
        public float $price,
        public ?float $logReturn,
        public ?float $residual,
        public ?float $residualMad,
        public int $observations,
        public ?float $units,
        public ?float $returnWeight,
        public bool $lowData,
        public bool $isUnusual,
    ) {}
}
