<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemBetaDto;
use App\Http\Resources\BaseResource;

final class ExchangeItemBetaResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ExchangeItemBetaDto $beta */
        $beta = $this->resource;

        return [
            'calculatedAt' => self::toDefaultResponseDatetime($beta->calculated_at),
            'beta' => $beta->beta,
            'rawBeta' => $beta->raw_beta,
            'rSquared' => $beta->r_squared,
            'observations' => $beta->observations,
        ];
    }
}
