<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck;

final readonly class ItemPriceCurrencyRowDto
{
    public function __construct(
        public string $slug,
        public string $name,
        public int $points,
    ) {}
}
