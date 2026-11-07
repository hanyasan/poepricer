<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

final class ExchangeMarketMetricDto
{
    public function __construct(
        public int $id,
        public string $bucket_start,
        public float $market_return,
        public int $basket_size,
        public int $poe_league_id,
        public int $exchange_metrics_item_id,
    ) {}
}
