<?php

namespace App\Jobs\Compact;

use App\Services\DataServices\Compact\ExchangeCompactServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Tries(1)]
final class BaseExchangeCompactJob implements ShouldQueue
{
    use Queueable;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping)->releaseAfter(300)->expireAfter(600)];
    }

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('compact');
    }

    /**
     * Execute the job.
     */
    public function handle(
        ExchangeCompactServiceContract $service,
    ): void {
        $service->compactPricesByBaseTimeRule();
    }
}
