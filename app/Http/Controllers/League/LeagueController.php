<?php

namespace App\Http\Controllers\League;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeagueItemsRequest;
use App\Http\Requests\LeagueOverviewRequest;
use App\Http\Requests\MarketDistributionRequest;
use App\Http\Resources\League\ItemsPage\LeagueItemsPageResource;
use App\Http\Resources\League\LeagueCurrencyResource;
use App\Http\Resources\League\LeagueItemCategoriesResource;
use App\Http\Resources\League\LeagueOverview\LeagueOverviewResource;
use App\Http\Resources\League\MainPage\MainPageLeagueResource;
use App\Http\Resources\League\MarketDistribution\MarketDistributionResource;
use App\Services\DataServices\Metrics\LeagueItemsServiceContract;
use App\Services\DataServices\Metrics\LeagueMarketServiceContract;
use App\Services\DataServices\Metrics\LeagueOverviewServiceContract;
use App\Services\DataServices\Poe\PoeLeagueServiceContract;
use Illuminate\Http\JsonResponse;

final class LeagueController extends Controller
{
    public function index(
        PoeLeagueServiceContract $poeLeagueService,
    ): JsonResponse {
        return response()->json(
            MainPageLeagueResource::collection(
                $poeLeagueService->mainPageLeagues()
            )
        );
    }

    public function currencies(
        string $leagueSlug,
        LeagueOverviewServiceContract $leagueOverviewService,
    ): JsonResponse {
        return response()->json(
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
        return response()->json(
            LeagueOverviewResource::make(
                $leagueOverviewService->overview($leagueSlug, $request->currency(), $request->period())
            )
        );
    }

    public function itemCategories(
        string $leagueSlug,
        LeagueItemsServiceContract $leagueItemsService,
    ): JsonResponse {
        return response()->json(
            LeagueItemCategoriesResource::make($leagueItemsService->categories($leagueSlug))
        );
    }

    public function items(
        string $leagueSlug,
        LeagueItemsRequest $request,
        LeagueItemsServiceContract $leagueItemsService,
    ): JsonResponse {
        return response()->json(
            LeagueItemsPageResource::make(
                $leagueItemsService->items($leagueSlug, $request->filter(), $request->sort(), $request->pagination())
            )
        );
    }

    public function distribution(
        string $leagueSlug,
        MarketDistributionRequest $request,
        LeagueMarketServiceContract $leagueMarketService,
    ): JsonResponse {
        return response()->json(
            MarketDistributionResource::make($leagueMarketService->distribution(
                $leagueSlug,
                $request->currency(),
                $request->bucket(),
                $request->includesThinlyTraded(),
            ))
        );
    }
}
