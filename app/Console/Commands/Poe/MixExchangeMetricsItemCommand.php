<?php

namespace App\Console\Commands\Poe;

use App\Enums\ExchangeMetricsMixStatusEnum;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Metrics\ExchangeMetricsItemSeedServiceContract;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[Signature('poe:metrics:mix-item
    {league : Id of the league from poe_leagues}
    {trade_id : Trade id of the exchange item of the league to add as a metrics currency}')]
#[Description('Add one currency to the metrics items of a league with the configured percentile of its latest prices as min price')]
final class MixExchangeMetricsItemCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        PoeLeagueRepositoryContract $leagueRepository,
        ExchangeMetricsItemSeedServiceContract $seedService,
    ): int {
        $argument = $this->argument('league');
        $tradeId = $this->argument('trade_id');

        if (! is_string($argument) || ! ctype_digit($argument)) {
            $this->error('The league must be a numeric id.');

            return self::FAILURE;
        }

        if (! is_string($tradeId) || $tradeId === '') {
            $this->error('The trade id must not be empty.');

            return self::FAILURE;
        }

        try {
            $league = $leagueRepository->find((int) $argument);
        } catch (ModelNotFoundException) {
            $this->error("League [{$argument}] does not exist.");

            return self::FAILURE;
        }

        $result = $seedService->mixItemForLeague($league->id, $tradeId);

        switch ($result->status) {
            case ExchangeMetricsMixStatusEnum::ItemNotFound:
                $this->error("League [{$league->league_name}] has no exchange item with trade id [{$tradeId}].");

                return self::FAILURE;

            case ExchangeMetricsMixStatusEnum::AlreadyExists:
                $this->info("[{$result->name}] is already a metrics item of league [{$league->league_name}], nothing to add.");

                return self::SUCCESS;

            case ExchangeMetricsMixStatusEnum::NoPriceCheck:
                $this->error("League [{$league->league_name}] has no price checks to take prices from.");

                return self::FAILURE;

            case ExchangeMetricsMixStatusEnum::NoItemPrice:
                $this->error("[{$result->name}] has no positive price in the latest price check of league [{$league->league_name}].");

                return self::FAILURE;

            case ExchangeMetricsMixStatusEnum::NoPricedItems:
                $this->error("The latest price check of league [{$league->league_name}] has no other priced items.");

                return self::FAILURE;

            case ExchangeMetricsMixStatusEnum::Added:
                $this->table(
                    ['Currency', 'Min price', 'Items'],
                    [[$result->name, $result->min_price, $result->sample_size]],
                );

                $this->info("Added [{$result->name}] to the metrics items of league [{$league->league_name}].");

                return self::SUCCESS;
        }
    }
}
