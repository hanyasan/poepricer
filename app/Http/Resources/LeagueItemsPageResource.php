<?php

namespace App\Http\Resources;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemPricePointDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemRowDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemsPageDto;

final class LeagueItemsPageResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueItemsPageDto $page */
        $page = $this->resource;

        return [
            'data' => array_map(static fn (LeagueItemRowDto $item): array => [
                'slug' => $item->slug,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $item->price,
                'logReturn' => $item->logReturn,
                'residual' => $item->residual,
                'residualMad' => $item->residualMad,
                'beta' => $item->beta,
                'observations' => $item->observations,
                'units' => $item->units,
                'returnWeight' => $item->returnWeight,
                'lowData' => $item->lowData,
                'isUnusual' => $item->isUnusual,
                'sparkline' => array_map(static fn (ItemPricePointDto $point): array => [
                    'bucket' => self::toDefaultResponseDatetime($point->bucket_start),
                    'value' => $point->price,
                ], $item->sparkline),
            ], $page->items),
            'meta' => [
                'currentPage' => $page->currentPage,
                'lastPage' => $page->lastPage,
                'perPage' => $page->perPage,
                'total' => $page->total,
                'currency' => $page->currency,
                'lastBucket' => self::toDefaultResponseDatetime($page->lastBucket),
            ],
        ];
    }
}
