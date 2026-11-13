<?php

namespace App\Http\Resources\League\MainPage;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\Http\Resources\BaseResource;

final class MarketReturnSeriesResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ExchangeMarketMetricDto $distribution */
        $metric = $this->resource;

        return [
            'bucket' => self::toDefaultResponseDatetimeBucket($metric->bucket_start),
            'value' => $metric->market_return,
        ];
    }
}
