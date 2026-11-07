<?php

namespace App\Http\Controllers\League;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainPageLeagueResource;
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
}
