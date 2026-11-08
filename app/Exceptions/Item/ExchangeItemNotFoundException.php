<?php

namespace App\Exceptions\Item;

use App\Exceptions\AppException;
use Illuminate\Http\JsonResponse;

final class ExchangeItemNotFoundException extends AppException
{
    protected $code = 404;

    protected $message = 'Item not found';

    public function render(): JsonResponse
    {
        return \response()->json(['message' => $this->getMessage()], 404);
    }
}
