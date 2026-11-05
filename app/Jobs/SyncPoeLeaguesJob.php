<?php

namespace App\Jobs;

use App\Services\NinjaImport\NinjaImportServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Tries(3)]
final class SyncPoeLeaguesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping)->releaseAfter(10)->expireAfter(600)];
    }

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('scheduled');
    }

    /**
     * Execute the job.
     */
    public function handle(
        NinjaImportServiceContract $ninjaImportService,
    ): void {
        $ninjaImportService->updateAllLeaguesFromNinja();
    }
}
