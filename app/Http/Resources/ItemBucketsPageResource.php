<?php

namespace App\Http\Resources;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemMetricRowDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemBucketsPageDto;

final class ItemBucketsPageResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemBucketsPageDto $page */
        $page = $this->resource;

        return [
            'data' => array_map(static fn (ItemMetricRowDto $row): array => [
                'bucket' => self::toDefaultResponseDatetime($row->bucket_start),
                'price' => $row->price,
                'logReturn' => $row->log_return,
                'residual' => $row->residual,
                'residualMad' => $row->residual_mad,
                'observations' => $row->observations,
                'units' => $row->units,
                'returnWeight' => $row->return_weight,
            ], $page->items),
            'meta' => [
                'currentPage' => $page->currentPage,
                'lastPage' => $page->lastPage,
                'perPage' => $page->perPage,
                'total' => $page->total,
                'currency' => $page->currency,
                'period' => $page->period->value,
            ],
        ];
    }
}
