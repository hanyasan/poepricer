<?php

namespace App\Http\Requests;

use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPricesQueryDto;
use App\Enums\ItemPriceWindowEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @psalm-suppress PropertyNotSetInConstructor request properties are set by the framework
 */
final class ItemPricesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:128'],
            'window' => ['nullable', Rule::enum(ItemPriceWindowEnum::class)],
        ];
    }

    public function pricesQuery(): ItemPricesQueryDto
    {
        $currency = $this->validated('currency');

        return new ItemPricesQueryDto(
            currency: is_string($currency) && $currency !== '' ? $currency : null,
            window: $this->enum('window', ItemPriceWindowEnum::class) ?? ItemPriceWindowEnum::TwoDays,
        );
    }
}
