<?php

namespace App\Exceptions\League;

use App\Exceptions\AppException;
use Illuminate\Http\JsonResponse;

final class LeagueNotFoundException extends AppException
{
    protected $code = 404;

    protected $message = 'League not found';

    public function render(): JsonResponse
    {
        return \response()->json(['message' => $this->getMessage()], 404);
    }
}
