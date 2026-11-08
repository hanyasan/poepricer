<?php

use App\Http\Controllers\League\LeagueController;
use App\Http\Controllers\League\LeagueItemController;
use App\Http\Controllers\League\LeagueMarketController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'leagues'], function () {
    Route::get('/', [LeagueController::class, 'index']);
    Route::get('{leagueSlug}/currencies', [LeagueController::class, 'currencies']);
    Route::get('{leagueSlug}/overview', [LeagueController::class, 'overview']);
    Route::get('{leagueSlug}/market/distribution', [LeagueMarketController::class, 'distribution']);
    Route::get('{leagueSlug}/items/categories', [LeagueController::class, 'itemCategories']);
    Route::get('{leagueSlug}/items', [LeagueController::class, 'items']);
    Route::get('{leagueSlug}/items/{itemSlug}/summary', [LeagueItemController::class, 'summary']);
    Route::get('{leagueSlug}/items/{itemSlug}/history', [LeagueItemController::class, 'history']);
    Route::get('{leagueSlug}/items/{itemSlug}/buckets', [LeagueItemController::class, 'buckets']);
});
