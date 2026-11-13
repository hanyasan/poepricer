<?php

namespace App\Http\Resources\League\MainPage;

use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use App\Http\Resources\BaseResource;

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
            'basketTotal' => $mainLeague->exchange_market_metrics === []
                ? 0
                : $mainLeague->exchange_market_metrics[array_key_last($mainLeague->exchange_market_metrics)]->basket_total,
            'marketReturnSeries' => MarketReturnSeriesResource::collection($mainLeague->exchange_market_metrics),
        ];
    }
}
