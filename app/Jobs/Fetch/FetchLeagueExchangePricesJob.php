<?php

namespace App\Jobs\Fetch;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\Services\NinjaImport\NinjaImportServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Tries(5)]
final class FetchLeagueExchangePricesJob implements ShouldQueue
{
    use Queueable;

    private ExtendedPoeLeagueDto $leagueDto;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('proceed_check_' . $this->leagueDto->id))
                ->releaseAfter(10)
                ->expireAfter(60)
                ->shared()
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        ExtendedPoeLeagueDto $leagueDto,
    )
    {
        $this->leagueDto = $leagueDto;

        $this->onQueue('scheduled');
    }

    /**
     * Execute the job.
     */
    public function handle(
        NinjaImportServiceContract $importService,
    ): void {
        $importService->checkExchangePrices($this->leagueDto);
    }
}
