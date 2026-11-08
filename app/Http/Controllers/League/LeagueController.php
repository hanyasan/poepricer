<?php

namespace App\Http\Controllers\League;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeagueOverviewRequest;
use App\Http\Resources\LeagueCurrencyResource;
use App\Http\Resources\LeagueOverviewResource;
use App\Http\Resources\MainPageLeagueResource;
use App\Services\DataServices\Metrics\LeagueOverviewServiceContract;
use App\Services\DataServices\Poe\PoeLeagueServiceContract;
use Illuminate\Http\JsonResponse;

final class LeagueController extends Controller
{
    public function index(
        PoeLeagueServiceContract $poeLeagueService,
    ): JsonResponse {
        return \response()->json(
            MainPageLeagueResource::collection(
                $poeLeagueService->mainPageLeagues()
            )
        );
    }

    public function currencies(
        string $leagueSlug,
        LeagueOverviewServiceContract $leagueOverviewService,
    ): JsonResponse {
        return \response()->json(
            LeagueCurrencyResource::collection(
                $leagueOverviewService->currencies($leagueSlug)
            )
        );
    }

    public function overview(
        string $leagueSlug,
        LeagueOverviewRequest $request,
        LeagueOverviewServiceContract $leagueOverviewService,
    ): JsonResponse {
        return \response()->json(
            new LeagueOverviewResource(
                $leagueOverviewService->overview($leagueSlug, $request->currency(), $request->period())
            )
        );
    }
}
