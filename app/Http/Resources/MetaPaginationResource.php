<?php

namespace App\Http\Resources;

use App\DataTransferObjects\ServiceObjects\PaginatedMetaDto;

final class MetaPaginationResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var PaginatedMetaDto $meta */
        $meta = $this->resource;

        return [
            'currentPage' => $meta->currentPage,
            'lastPage' => $meta->lastPage,
            'perPage' => $meta->perPage,
            'total' => $meta->total,
        ];
    }
}
