<?php

namespace App\Http\Resources\League\ItemsPage;

use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemsPageDto;
use App\Http\Resources\BaseResource;
use App\Http\Resources\MetaPaginationResource;

final class LeagueItemsPageResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueItemsPageDto $page */
        $page = $this->resource;

        return [
            'currency' => $page->currency,
            'lastBucket' => self::toDefaultResponseDatetime($page->lastBucket),
            'lastSnapshotAt' => self::toDefaultResponseDatetime($page->lastSnapshotAt),
            'data' => LeagueItemRowResource::collection($page->items),
            'meta' => MetaPaginationResource::make($page->meta),
        ];
    }
}
