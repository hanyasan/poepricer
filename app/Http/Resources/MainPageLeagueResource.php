<?php

namespace App\Http\Resources;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;

final class MainPageLeagueResource extends JsonResource
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
            'lastSnapshotAt' => isset($mainLeague->latest_price_check[0])
                ? CarbonImmutable::parse($mainLeague->latest_price_check[0]->created_at)->toIso8601String()
                : null,
            'basketSize' => $mainLeague->exchange_market_metrics === []
                ? 0
                : $mainLeague->exchange_market_metrics[array_key_last($mainLeague->exchange_market_metrics)]->basket_size,
            'marketReturnSeries' => array_map(static fn (ExchangeMarketMetricDto $metric) => [
                'bucket' => CarbonImmutable::parse($metric->bucket_start)->toIso8601String(),
                'value' => $metric->market_return,
            ], $mainLeague->exchange_market_metrics),
        ];
    }
}
