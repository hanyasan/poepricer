<?php

namespace App\Http\Requests;

use App\Enums\MetricPeriodEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @psalm-suppress PropertyNotSetInConstructor request properties are set by the framework
 */
final class LeagueOverviewRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:128'],
            'period' => ['nullable', Rule::enum(MetricPeriodEnum::class)],
        ];
    }

    public function currency(): ?string
    {
        $currency = $this->validated('currency');

        return is_string($currency) && $currency !== '' ? $currency : null;
    }

    public function period(): MetricPeriodEnum
    {
        return $this->enum('period', MetricPeriodEnum::class) ?? MetricPeriodEnum::Week;
    }
}
