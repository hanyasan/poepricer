<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @psalm-suppress PropertyNotSetInConstructor request properties are set by the framework
 */
final class ItemSummaryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function currency(): ?string
    {
        $currency = $this->validated('currency');

        return is_string($currency) && $currency !== '' ? $currency : null;
    }
}
