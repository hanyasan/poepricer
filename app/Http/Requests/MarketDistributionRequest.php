<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @psalm-suppress PropertyNotSetInConstructor request properties are set by the framework
 */
final class MarketDistributionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:128'],
            'bucket' => ['nullable', 'date'],
            'all' => ['nullable', 'boolean'],
        ];
    }

    public function currency(): ?string
    {
        $currency = $this->validated('currency');

        return is_string($currency) && $currency !== '' ? $currency : null;
    }

    public function bucket(): ?CarbonImmutable
    {
        $bucket = $this->validated('bucket');

        return is_string($bucket) && $bucket !== '' ? CarbonImmutable::parse($bucket)->utc() : null;
    }

    public function includesThinlyTraded(): bool
    {
        return $this->boolean('all');
    }
}
