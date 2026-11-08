<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    /**
     * Formats a moment as ISO 8601 with the time zone, the datetime format of every response.
     *
     * @return ($moment is null ? null : string)
     */
    protected static function toDefaultResponseDatetime(string|CarbonInterface|null $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        return CarbonImmutable::parse($moment)->toIso8601String();
    }
}
