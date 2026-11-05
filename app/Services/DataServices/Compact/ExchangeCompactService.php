<?php

namespace App\Services\DataServices\Compact;

use App\Repositories\Poe\Exchange\Compact\ExchangeCompactRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeCompactService implements ExchangeCompactServiceContract
{
    public function __construct(
        private ExchangeCompactRepositoryContract $repository,
        private PriceCheckRepositoryContract $priceCheckRepository,
    ) {}

    #[\Override]
    public function compactPricesByBaseTimeRule(): void
    {
        $priceCheckDataToCompact = $this->repository->getLeagueDailyPeriodsPriceCheckIds(
            Carbon::now('UTC')->startOfDay()->subDays(config('poe.compact.days_stay')),
        );

        if (empty($priceCheckDataToCompact)) {
            return;
        }

        DB::transaction(function () use ($priceCheckDataToCompact) {
            foreach ($this->groupPeriodsByLeagueAndDate($priceCheckDataToCompact) as $leagueDaysCompact) {
                $this->repository->makeCompactPriceCheck(
                    $leagueDaysCompact['poe_league_id'],
                    $leagueDaysCompact['date'],
                    $this->repository->getAvgCoreTypesData($leagueDaysCompact['price_checks']),
                    $this->repository->getAvgCorePricesData($leagueDaysCompact['price_checks']),
                );

                $this->priceCheckRepository->deleteMany($leagueDaysCompact['price_checks']);
            }
        });
    }

    /**
     * @param array<int, array{
     *     id: int,
     *     poe_league_id: int,
     *     date: string,
     * }> $periods
     * @return array<array-key, array{
     *     date: string,
     *     poe_league_id: int,
     *     price_checks: int[]
     * }>
     */
    private function groupPeriodsByLeagueAndDate(array $periods): array
    {
        return collect($periods)
            ->groupBy(fn (array $period) => $period['date'].' '.$period['poe_league_id'])
            ->map(fn ($grouped) => [
                'date' => $grouped[0]['date'],
                'poe_league_id' => $grouped[0]['poe_league_id'],
                'price_checks' => $grouped->pluck('id')->toArray(),
            ])
            ->toArray();
    }
}
