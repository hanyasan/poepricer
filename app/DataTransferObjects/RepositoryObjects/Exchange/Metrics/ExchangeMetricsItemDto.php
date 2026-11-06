<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;

final readonly class ExchangeMetricsItemDto
{
    public function __construct(
        public int $id,
        public float $min_price,
        public int $poe_league_id,
        public int $exchange_item_id,
        public ExchangeItemDto $exchange_item,
    ) {}
}
