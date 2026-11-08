<?php

namespace App\Http\Requests;

use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemDetailQueryDto;
use App\Enums\MetricPeriodEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @psalm-suppress PropertyNotSetInConstructor request properties are set by the framework
 */
final class ItemHistoryRequest extends FormRequest
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

    public function detailQuery(): ItemDetailQueryDto
    {
        $currency = $this->validated('currency');

        return new ItemDetailQueryDto(
            currency: is_string($currency) && $currency !== '' ? $currency : null,
            period: $this->enum('period', MetricPeriodEnum::class) ?? MetricPeriodEnum::Week,
        );
    }
}
