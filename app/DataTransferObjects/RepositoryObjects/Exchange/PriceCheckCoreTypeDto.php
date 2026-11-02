<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange;

final readonly class PriceCheckCoreTypeDto
{
    public function __construct(
        public int $id,
        public float $rate,
        public string $exchange_item_trade_id,
        public string $name,
        public int $price_check_core_id,
    ) {}
}
