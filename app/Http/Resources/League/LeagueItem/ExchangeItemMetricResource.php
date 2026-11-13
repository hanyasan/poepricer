<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemMetricRowDto;
use App\Http\Resources\BaseResource;

final class ExchangeItemMetricResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemMetricRowDto $itemMetricRow */
        $itemMetricRow = $this->resource;

        return [
            'bucket' => self::toDefaultResponseDatetimeBucket($itemMetricRow->bucket_start),
            'price' => $itemMetricRow->price,
            'logReturn' => $itemMetricRow->log_return,
            'residual' => $itemMetricRow->residual,
            'residualMad' => $itemMetricRow->residual_mad,
            'observations' => $itemMetricRow->observations,
            'units' => $itemMetricRow->units,
            'returnWeight' => $itemMetricRow->return_weight,
        ];
    }
}
