<?php

namespace App\Http\Resources\League\ItemsPage;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemPricePointDto;
use App\Http\Resources\BaseResource;

final class ItemPricePointResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemPricePointDto $point */
        $point = $this->resource;

        return [
            'bucket' => self::toDefaultResponseDatetimeBucket($point->bucket_start),
            'value' => $point->price,
        ];
    }
}
