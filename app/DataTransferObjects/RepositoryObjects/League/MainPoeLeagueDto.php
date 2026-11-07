<?php

namespace App\DataTransferObjects\RepositoryObjects\League;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;

final readonly class MainPoeLeagueDto
{
    /**
     * @param  PriceCheckDto[]|null  $latest_price_check
     * @param  ExchangeMarketMetricDto[]  $exchange_market_metrics
     */
    public function __construct(
        public int $id,
        public string $league_name,
        public string $ninja_league_id,
        public string $slug,
        public bool $is_active,
        public int $poe_version_id,
        public PoeVersionDto $poe_version,
        public array $exchange_market_metrics,
        public ?array $latest_price_check = null,
    ) {}
}
