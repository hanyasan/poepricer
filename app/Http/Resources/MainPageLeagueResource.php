<?php

namespace App\Http\Resources;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;

final class MainPageLeagueResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var MainPoeLeagueDto $mainLeague */
        $mainLeague = $this->resource;

        return [
            'game' => $mainLeague->poe_version->version,
            'slug' => $mainLeague->slug,
            'name' => $mainLeague->league_name,
            'isActive' => $mainLeague->is_active,
            'lastSnapshotAt' => self::toDefaultResponseDatetime(
                $mainLeague->latest_price_check[0]->created_at ?? null
            ),
            'basketSize' => $mainLeague->exchange_market_metrics === []
                ? 0
                : $mainLeague->exchange_market_metrics[array_key_last($mainLeague->exchange_market_metrics)]->basket_size,
            'marketReturnSeries' => array_map(static fn (ExchangeMarketMetricDto $metric) => [
                'bucket' => self::toDefaultResponseDatetime($metric->bucket_start),
                'value' => $metric->market_return,
            ], $mainLeague->exchange_market_metrics),
        ];
    }
}
