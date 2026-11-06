<?php

use App\Jobs\Compact\BaseExchangeCompactJob;
use App\Jobs\Fetch\FetchPoeOnePricesJob;
use App\Jobs\Fetch\FetchPoeTwoPricesJob;
use App\Jobs\Metrics\CollectBucketMetricsJob;
use App\Jobs\Metrics\RecalculateBetasJob;
use App\Jobs\SyncPoeLeaguesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(SyncPoeLeaguesJob::class)->dailyAt('23:00');
        $schedule->job(FetchPoeOnePricesJob::class)->everyTenMinutes();
        $schedule->job(FetchPoeTwoPricesJob::class)->everyThirtyMinutes();
        $schedule->job(CollectBucketMetricsJob::class)->everySixHours();
        $schedule->job(RecalculateBetasJob::class)->dailyAt('20:00');
        $schedule->job(BaseExchangeCompactJob::class)->weeklyOn(1);
        $schedule->job(BaseExchangeCompactJob::class)->weeklyOn(4);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
