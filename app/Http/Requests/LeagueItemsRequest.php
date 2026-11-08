<?php

namespace App\Http\Requests;

use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsFilterDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsSortDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @psalm-suppress PropertyNotSetInConstructor request properties are set by the framework
 */
final class LeagueItemsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:128'],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => [
                'nullable',
                'string',
                'max:255',
                Rule::prohibitedIf(fn (): bool => $this->boolean('uncategorized')),
            ],
            'uncategorized' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ItemsSortDto::fromQueryValue($value) === null) {
                    $fail('The selected sort is invalid.');
                }
            }],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('poe.items.max_per_page')],
        ];
    }

    public function filter(): ItemsFilterDto
    {
        return new ItemsFilterDto(
            currency: $this->stringValue('currency'),
            search: $this->stringValue('search'),
            category: $this->stringValue('category'),
            uncategorized: $this->boolean('uncategorized'),
        );
    }

    public function sort(): ItemsSortDto
    {
        $sort = $this->stringValue('sort');

        return ($sort === null ? null : ItemsSortDto::fromQueryValue($sort)) ?? new ItemsSortDto;
    }

    public function pagination(): PaginationDto
    {
        return new PaginationDto(
            page: $this->integer('page', 1),
            perPage: $this->integer('perPage', (int) config('poe.items.per_page')),
        );
    }

    private function stringValue(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
