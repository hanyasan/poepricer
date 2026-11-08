<?php

use App\Http\Controllers\League\LeagueController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'leagues'], function () {
    Route::get('/', [LeagueController::class, 'index']);
    Route::get('{leagueSlug}/currencies', [LeagueController::class, 'currencies']);
    Route::get('{leagueSlug}/overview', [LeagueController::class, 'overview']);
    Route::get('{leagueSlug}/items/categories', [LeagueController::class, 'itemCategories']);
    Route::get('{leagueSlug}/items', [LeagueController::class, 'items']);
});
