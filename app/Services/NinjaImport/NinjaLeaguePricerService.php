<?php

namespace App\Services\NinjaImport;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final readonly class NinjaLeaguePricerService implements NinjaLeaguePricerServiceContract
{
    public function __construct(
        private NinjaImportServiceContract $importService,
        private PoeLeagueRepositoryContract $leagueRepository,
    ) {}

    #[\Override]
    public function importPickedForVersion(PoeVersionDto $version): void
    {
        $pickedLeagues = $this->pickLeaguesForCheck($version);
        $leaguePriced = 0;

        foreach ($pickedLeagues as $pickedLeague) {
            try {
                $this->importService->checkExchangePrices($pickedLeague);

                $leaguePriced += 1;
            } catch (\Throwable $exception) {
                Log::error('Import exchange prices for league '.$pickedLeague->league_name.' failed');
            }
        }

        Log::info('Imported '.$leaguePriced.' of '.count($pickedLeagues).' leagues for '.$version->version);
    }

    /**
     * A league is due when the schedule slot of its latest check plus the rule interval has been reached.
     * Rounding the check time down to the scheduler step makes the result independent of
     * how long the queue and the import took within the slot.
     *
     * @return Collection<int, ExtendedPoeLeagueDto>
     */
    #[\Override]
    public function pickLeaguesForCheck(PoeVersionDto $version): Collection
    {
        $stepMinutes = config('poe.price_check.schedule_step_minutes.'.$version->version, 10);

        /** @var Collection<int, ExtendedPoeLeagueDto> $collection */
        $collection = collect($this->leagueRepository->getWithLastPriceCheckAndRule($version->id))
            ->filter(function (ExtendedPoeLeagueDto $poeLeagueDto) use ($stepMinutes) {
                $minutesCheck = $poeLeagueDto->check_rule?->minutes_check;
                $latestCheck = ($poeLeagueDto->latest_price_check[0] ?? null)?->created_at;

                if ($minutesCheck === null) {
                    return false;
                }

                if ($latestCheck === null) {
                    return true;
                }

                $latestSlot = CarbonImmutable::parse($latestCheck)->utc()->floorMinutes($stepMinutes);

                return CarbonImmutable::now()->greaterThanOrEqualTo($latestSlot->addMinutes($minutesCheck));
            });

        return $collection;
    }
}
