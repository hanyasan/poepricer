<?php

namespace App\Console\Commands\Poe;

use App\Enums\ExchangeMetricsSeedStatusEnum;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Metrics\ExchangeMetricsItemSeedServiceContract;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[Signature('poe:metrics:seed-items
    {league : Id of the league from poe_leagues}')]
#[Description('Seed the metrics items of a league that has none from the core types of its latest price check')]
final class SeedExchangeMetricsItemsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        PoeLeagueRepositoryContract $leagueRepository,
        ExchangeMetricsItemSeedServiceContract $seedService,
    ): int {
        $argument = $this->argument('league');

        if (! is_string($argument) || ! ctype_digit($argument)) {
            $this->error('The league must be a numeric id.');

            return self::FAILURE;
        }

        try {
            $league = $leagueRepository->find((int) $argument);
        } catch (ModelNotFoundException) {
            $this->error("League [{$argument}] does not exist.");

            return self::FAILURE;
        }

        $result = $seedService->seedForLeague($league->id);

        switch ($result->status) {
            case ExchangeMetricsSeedStatusEnum::AlreadySeeded:
                $this->info("League [{$league->league_name}] already has metrics items, nothing to seed.");

                return self::SUCCESS;

            case ExchangeMetricsSeedStatusEnum::NoPriceCheck:
                $this->error("League [{$league->league_name}] has no price checks to seed from.");

                return self::FAILURE;

            case ExchangeMetricsSeedStatusEnum::NoPricedItems:
                $this->error("The latest price check of league [{$league->league_name}] has no priced items to seed from.");

                return self::FAILURE;

            case ExchangeMetricsSeedStatusEnum::Seeded:
                $this->table(
                    ['Currency', 'Min price', 'Items'],
                    array_map(
                        static fn (array $item): array => [$item['name'], $item['min_price'], $item['sample_size']],
                        $result->items,
                    ),
                );

                $this->info('Seeded '.count($result->items)." metrics items for league [{$league->league_name}].");

                return self::SUCCESS;
        }
    }
}
