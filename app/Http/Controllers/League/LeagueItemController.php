<?php

namespace App\Http\Controllers\League;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemBucketsRequest;
use App\Http\Requests\ItemHistoryRequest;
use App\Http\Requests\ItemPricesRequest;
use App\Http\Requests\ItemSummaryRequest;
use App\Http\Resources\League\LeagueItem\ItemBucketsPageResource;
use App\Http\Resources\League\LeagueItem\ItemHistoryResource;
use App\Http\Resources\League\LeagueItem\ItemPricesResource;
use App\Http\Resources\League\LeagueItem\ItemSummaryResource;
use App\Services\DataServices\Metrics\LeagueItemDetailServiceContract;
use App\Services\DataServices\Prices\LeagueItemPricesServiceContract;
use Illuminate\Http\JsonResponse;

final class LeagueItemController extends Controller
{
    public function summary(
        string $leagueSlug,
        string $itemSlug,
        ItemSummaryRequest $request,
        LeagueItemDetailServiceContract $itemDetailService,
    ): JsonResponse {
        return response()->json(
            ItemSummaryResource::make($itemDetailService->summary($leagueSlug, $itemSlug, $request->currency()))
        );
    }

    public function history(
        string $leagueSlug,
        string $itemSlug,
        ItemHistoryRequest $request,
        LeagueItemDetailServiceContract $itemDetailService,
    ): JsonResponse {
        return response()->json(
            ItemHistoryResource::make($itemDetailService->history($leagueSlug, $itemSlug, $request->detailQuery()))
        );
    }

    public function prices(
        string $leagueSlug,
        string $itemSlug,
        ItemPricesRequest $request,
        LeagueItemPricesServiceContract $itemPricesService,
    ): JsonResponse {
        return response()->json(
            ItemPricesResource::make($itemPricesService->prices($leagueSlug, $itemSlug, $request->pricesQuery()))
        );
    }

    public function buckets(
        string $leagueSlug,
        string $itemSlug,
        ItemBucketsRequest $request,
        LeagueItemDetailServiceContract $itemDetailService,
    ): JsonResponse {
        return response()->json(
            ItemBucketsPageResource::make(
                $itemDetailService->buckets($leagueSlug, $itemSlug, $request->detailQuery(), $request->pagination())
            )
        );
    }
}
