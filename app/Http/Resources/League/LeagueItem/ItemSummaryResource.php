<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemSummaryDto;
use App\Http\Resources\BaseResource;

final class ItemSummaryResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemSummaryDto $summary */
        $summary = $this->resource;
        $current = $summary->current;

        return [
            'item' => [
                'slug' => $summary->item->slug,
                'name' => $summary->item->name,
                'category' => $summary->item->category,
            ],
            'currency' => $summary->currency,
            'lastBucket' => self::toDefaultResponseDatetimeBucket($summary->lastBucket),
            'currentPrice' => $summary->currentPrice,
            'currentPriceAt' => self::toDefaultResponseDatetime($summary->currentPriceAt),
            'current' => $current === null ? null : [
                'price' => $current->price,
                'logReturn' => $current->logReturn,
                'residual' => $current->residual,
                'residualMad' => $current->residualMad,
                'observations' => $current->observations,
                'units' => $current->units,
                'returnWeight' => $current->returnWeight,
                'lowData' => $current->lowData,
                'isUnusual' => $current->isUnusual,
            ],
            'beta' => $summary->beta === null ? null : ExchangeItemBetaResource::make($summary->beta),
        ];
    }
}
