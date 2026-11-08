<?php

namespace App\Http\Controllers\League;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarketDistributionRequest;
use App\Http\Resources\MarketDistributionResource;
use App\Services\DataServices\Metrics\LeagueMarketServiceContract;
use Illuminate\Http\JsonResponse;

final class LeagueMarketController extends Controller
{
    public function distribution(
        string $leagueSlug,
        MarketDistributionRequest $request,
        LeagueMarketServiceContract $leagueMarketService,
    ): JsonResponse {
        return \response()->json(
            new MarketDistributionResource($leagueMarketService->distribution(
                $leagueSlug,
                $request->currency(),
                $request->bucket(),
                $request->includesThinlyTraded(),
            ))
        );
    }
}
