<?php

namespace App\Console\Commands\Poe;

use App\Jobs\Fetch\FetchPoeOnePricesJob;
use App\Jobs\Fetch\FetchPoeTwoPricesJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[Signature('poe:fetch-all')]
#[Description('Dispatch the jobs that fetch exchange prices for PoE1 and PoE2 leagues that are due for a check')]
final class FetchAllPricesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        FetchPoeOnePricesJob::dispatch();
        $this->line('Dispatched the PoE1 price fetch job.');

        FetchPoeTwoPricesJob::dispatch();
        $this->line('Dispatched the PoE2 price fetch job.');

        $this->info('Price fetch jobs are queued.');

        return self::SUCCESS;
    }
}
