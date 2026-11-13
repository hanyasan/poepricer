<?php

namespace App\Http\Resources\League\LeagueOverview;

use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueOverviewDto;
use App\Http\Resources\BaseResource;
use App\Http\Resources\League\MainPage\MarketReturnSeriesResource;

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
            'lastBucket' => self::toDefaultResponseDatetimeBucket($overview->lastBucket),
            'kpi' => [
                'marketReturnLastBucket' => $overview->marketReturnLastBucket,
                'marketReturn7d' => $overview->marketReturn7d,
                'basketSize' => $overview->basketSize,
                'basketTotal' => $overview->basketTotal,
                'lastSnapshotAt' => self::toDefaultResponseDatetime($overview->lastSnapshotAt),
            ],
            'marketReturnSeries' => MarketReturnSeriesResource::collection($overview->marketReturnSeries),
            'unusualMoves' => LeagueUnusualMoveResource::collection($overview->unusualMoves),
            'topGainers' => LeagueItemMoveResource::collection($overview->topGainers),
            'topLosers' => LeagueItemMoveResource::collection($overview->topLosers),
        ];
    }
}
