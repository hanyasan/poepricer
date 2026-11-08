<?php

namespace App\Http\Resources;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueItemMoveDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueOverviewDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueUnusualMoveDto;

final class LeagueOverviewResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueOverviewDto $overview */
        $overview = $this->resource;

        return [
            'currency' => $overview->currency,
            'period' => $overview->period->value,
            'lastBucket' => self::toDefaultResponseDatetime($overview->lastBucket),
            'kpi' => [
                'marketReturn12h' => $overview->marketReturn12h,
                'marketReturn7d' => $overview->marketReturn7d,
                'basketSize' => $overview->basketSize,
                'lastSnapshotAt' => self::toDefaultResponseDatetime($overview->lastSnapshotAt),
            ],
            'marketReturnSeries' => array_map(static fn (ExchangeMarketMetricDto $metric): array => [
                'bucket' => self::toDefaultResponseDatetime($metric->bucket_start),
                'value' => $metric->market_return,
            ], $overview->marketReturnSeries),
            'unusualMoves' => array_map(static fn (LeagueUnusualMoveDto $move): array => [
                'slug' => $move->slug,
                'name' => $move->name,
                'price' => $move->price,
                'logReturn' => $move->logReturn,
                'residual' => $move->residual,
                'residualMad' => $move->residualMad,
                'ratio' => $move->ratio,
                'beta' => $move->beta,
                'observations' => $move->observations,
            ], $overview->unusualMoves),
            'topGainers' => $this->itemMoves($overview->topGainers),
            'topLosers' => $this->itemMoves($overview->topLosers),
        ];
    }

    /**
     * @param  LeagueItemMoveDto[]  $moves
     * @return array<int, array{slug: string, name: string, price: float, logReturn: float}>
     */
    private function itemMoves(array $moves): array
    {
        return array_map(static fn (LeagueItemMoveDto $move): array => [
            'slug' => $move->slug,
            'name' => $move->name,
            'price' => $move->price,
            'logReturn' => $move->logReturn,
        ], $moves);
    }
}
