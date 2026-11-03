<?php

namespace App\Console\Commands\Poe;

use App\Services\NinjaImport\NinjaImportServiceContract;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[Signature('poe:update-leagues-from-ninja')]
#[Description('Sync PoE leagues of every version from poe.ninja')]
final class UpdateLeaguesFromNinjaCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NinjaImportServiceContract $service): int
    {
        $this->info('Updating leagues from poe.ninja');

        $service->updateAllLeaguesFromNinja();

        $this->info('Leagues updated');

        return self::SUCCESS;
    }
}
