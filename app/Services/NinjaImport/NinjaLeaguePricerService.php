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
                Log::error('Import exchange prices for league ' . $pickedLeague->league_name . ' failed');
            }
        }

        Log::info('Imported ' . $leaguePriced . ' of ' . count($pickedLeagues) . ' leagues for ' . $version->version);
    }

    /**
     * @return Collection<int, ExtendedPoeLeagueDto>
     */
    private function pickLeaguesForCheck(PoeVersionDto $version): Collection
    {
        /** @var Collection<int, ExtendedPoeLeagueDto> $collection */
        $collection = collect($this->leagueRepository->getWithLastPriceCheckAndRule($version->id))
            ->filter(function (ExtendedPoeLeagueDto $poeLeagueDto) {
                $minutesCheck = $poeLeagueDto->check_rule?->minutes_check;
                $latestCheck = ($poeLeagueDto->latest_price_check[0] ?? null)?->created_at;

                if ($minutesCheck === null) {
                    return false;
                }

                return is_null($latestCheck) || CarbonImmutable::now()->greaterThanOrEqualTo(
                    CarbonImmutable::parse($latestCheck)->addMinutes($minutesCheck)
                );
            });

        return $collection;
    }
}
