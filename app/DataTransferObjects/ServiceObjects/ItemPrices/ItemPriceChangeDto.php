<?php

namespace App\DataTransferObjects\ServiceObjects\ItemPrices;

final readonly class ItemPriceChangeDto
{
    public function __construct(
        public int $hours,
        public ?float $change,
    ) {}
}
