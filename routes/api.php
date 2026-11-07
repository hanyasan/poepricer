<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'metrics'], function () {
    Route::group(['prefix' => 'leagues'], function () {
//        Route::get('/leagues',  [Metrics::class, 'index']);
    });
});
