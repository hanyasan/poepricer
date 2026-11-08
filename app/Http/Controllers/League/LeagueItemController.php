<?php

namespace App\Http\Controllers\League;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemBucketsRequest;
use App\Http\Requests\ItemHistoryRequest;
use App\Http\Requests\ItemSummaryRequest;
use App\Http\Resources\ItemBucketsPageResource;
use App\Http\Resources\ItemHistoryResource;
use App\Http\Resources\ItemSummaryResource;
use App\Services\DataServices\Metrics\LeagueItemDetailServiceContract;
use Illuminate\Http\JsonResponse;

final class LeagueItemController extends Controller
{
    public function summary(
        string $leagueSlug,
        string $itemSlug,
        ItemSummaryRequest $request,
        LeagueItemDetailServiceContract $itemDetailService,
    ): JsonResponse {
        return \response()->json(
            new ItemSummaryResource($itemDetailService->summary($leagueSlug, $itemSlug, $request->currency()))
        );
    }

    public function history(
        string $leagueSlug,
        string $itemSlug,
        ItemHistoryRequest $request,
        LeagueItemDetailServiceContract $itemDetailService,
    ): JsonResponse {
        return \response()->json(
            new ItemHistoryResource($itemDetailService->history($leagueSlug, $itemSlug, $request->detailQuery()))
        );
    }

    public function buckets(
        string $leagueSlug,
        string $itemSlug,
        ItemBucketsRequest $request,
        LeagueItemDetailServiceContract $itemDetailService,
    ): JsonResponse {
        return \response()->json(
            new ItemBucketsPageResource(
                $itemDetailService->buckets($leagueSlug, $itemSlug, $request->detailQuery(), $request->pagination())
            )
        );
    }
}
