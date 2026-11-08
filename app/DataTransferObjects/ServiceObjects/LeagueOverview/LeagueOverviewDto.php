<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueOverview;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\Enums\MetricPeriodEnum;

final readonly class LeagueOverviewDto
{
    /**
     * @param  ExchangeMarketMetricDto[]  $marketReturnSeries
     * @param  LeagueUnusualMoveDto[]  $unusualMoves
     * @param  LeagueItemMoveDto[]  $topGainers
     * @param  LeagueItemMoveDto[]  $topLosers
     */
    public function __construct(
        public ?string $currency,
        public MetricPeriodEnum $period,
        public ?string $lastBucket,
        public ?float $marketReturnLastBucket,
        public ?float $marketReturn7d,
        public int $basketSize,
        public int $basketTotal,
        public ?string $lastSnapshotAt,
        public array $marketReturnSeries = [],
        public array $unusualMoves = [],
        public array $topGainers = [],
        public array $topLosers = [],
    ) {}
}
