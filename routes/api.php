<?php

use App\Http\Controllers\League\LeagueController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'leagues'], function () {
    Route::get('/', [LeagueController::class, 'index']);
});
