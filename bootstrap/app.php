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
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // The price fetches keep their exact minutes: the league check rules are counted from
        // them and round the check time down to the schedule step. Everything else is offset,
        // so a heavy job does not land on the same minute as a fetch.
        $schedule->job(SyncPoeLeaguesJob::class)->dailyAt('23:04');
        $schedule->job(FetchPoeOnePricesJob::class)->everyTenMinutes();
        $schedule->job(FetchPoeTwoPricesJob::class)->everyThirtyMinutes();
        $schedule->job(CollectBucketMetricsJob::class)->everySixHours(2);
        $schedule->job(RecalculateBetasJob::class)->dailyAt('20:05');
        $schedule->job(BaseExchangeCompactJob::class)->weeklyOn(1, '0:07');
        $schedule->job(BaseExchangeCompactJob::class)->weeklyOn(4, '0:07');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
