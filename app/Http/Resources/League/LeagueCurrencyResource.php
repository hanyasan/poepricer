<?php

namespace App\Http\Resources\League;

use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueCurrencyDto;
use Illuminate\Http\Resources\Json\JsonResource;

final class LeagueCurrencyResource extends JsonResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueCurrencyDto $currency */
        $currency = $this->resource;

        return [
            'slug' => $currency->slug,
            'name' => $currency->name,
        ];
    }
}
