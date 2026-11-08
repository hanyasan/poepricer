<?php

namespace App\Http\Requests;

use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemDetailQueryDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Enums\MetricPeriodEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @psalm-suppress PropertyNotSetInConstructor request properties are set by the framework
 */
final class ItemBucketsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:128'],
            'period' => ['nullable', Rule::enum(MetricPeriodEnum::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('poe.items.buckets_max_per_page')],
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

    public function pagination(): PaginationDto
    {
        return new PaginationDto(
            page: $this->integer('page', 1),
            perPage: $this->integer('perPage', (int) config('poe.items.buckets_per_page')),
        );
    }
}
