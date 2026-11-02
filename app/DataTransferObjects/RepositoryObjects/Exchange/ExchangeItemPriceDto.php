<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange;

final readonly class ExchangeItemPriceDto
{
    public function __construct(
        public int $id,
        public float $rate,
    ) {}
}
