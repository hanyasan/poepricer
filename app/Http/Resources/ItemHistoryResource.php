<?php

namespace App\Http\Resources;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemBetaDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemSeriesRowDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemHistoryDto;

final class ItemHistoryResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemHistoryDto $history */
        $history = $this->resource;

        return [
            'currency' => $history->currency,
            'period' => $history->period->value,
            'lastBucket' => self::toDefaultResponseDatetime($history->lastBucket),
            'series' => array_map(static fn (ItemSeriesRowDto $point): array => [
                'bucket' => self::toDefaultResponseDatetime($point->bucket_start),
                'price' => $point->price,
                'residual' => $point->residual,
                'residualMad' => $point->residual_mad,
            ], $history->series),
            'betaHistory' => array_map(self::beta(...), $history->betaHistory),
        ];
    }

    /**
     * @return array{calculatedAt: string, beta: float, rawBeta: float, rSquared: float|null, observations: int}
     */
    public static function beta(ExchangeItemBetaDto $beta): array
    {
        return [
            'calculatedAt' => self::toDefaultResponseDatetime($beta->calculated_at),
            'beta' => $beta->beta,
            'rawBeta' => $beta->raw_beta,
            'rSquared' => $beta->r_squared,
            'observations' => $beta->observations,
        ];
    }
}
