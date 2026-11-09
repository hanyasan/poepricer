<?php

namespace App\Http\Resources;

use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemSummaryDto;

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
            'lastBucket' => self::toDefaultResponseDatetime($summary->lastBucket),
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
            'beta' => $summary->beta === null ? null : ItemHistoryResource::beta($summary->beta),
        ];
    }
}
