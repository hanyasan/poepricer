<?php

use App\Http\Controllers\League\LeagueController;
use App\Http\Controllers\League\LeagueItemController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'leagues'], function () {
    Route::get('/', [LeagueController::class, 'index']);

    Route::group(['prefix' => '{leagueSlug}'], function () {
        Route::get('/currencies', [LeagueController::class, 'currencies']);
        Route::get('/overview', [LeagueController::class, 'overview']);
        Route::get('/market/distribution', [LeagueController::class, 'distribution']);

        Route::group(['prefix' => 'items'], function () {
            Route::get('/', [LeagueController::class, 'items']);
            Route::get('/categories', [LeagueController::class, 'itemCategories']);

            Route::group(['prefix' => '{itemSlug}'], function () {
                Route::get('/summary', [LeagueItemController::class, 'summary']);
                Route::get('/history', [LeagueItemController::class, 'history']);
                Route::get('/prices', [LeagueItemController::class, 'prices']);
                Route::get('/buckets', [LeagueItemController::class, 'buckets']);
            });
        });
    });
});
