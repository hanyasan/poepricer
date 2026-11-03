<?php

namespace App\Services\NinjaImport;

use App\DataTransferObjects\ParsingObjects\PoeNinjaLeague;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Exceptions\Mapping\MapInvalidSourceException;
use App\Exceptions\Mapping\MappingErrorException;
use App\HttpClients\NinjaClient;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Poe\PoeVersionServiceContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class NinjaImportService implements NinjaImportServiceContract
{
    public function __construct(
        private PoeLeagueRepositoryContract $poeLeagueRepository,
        private PoeVersionServiceContract $poeVersionService,
        private NinjaClient $ninjaClient,
    ) {}

    #[\Override]
    public function updateAllLeaguesFromNinja(): void
    {
        $versions = $this->poeVersionService->getAll();

        foreach ($versions as $version) {
            try {
                $this->updateLeaguesFromNinjaByVersion($version);
            } catch (\Throwable $exception) {
                Log::error('Unable to update leagues for version '.
                    $version->version.
                    ' - message: '.
                    $exception->getMessage()
                );
            }
        }
    }

    /**
     * @throws MappingErrorException
     * @throws ConnectionException
     * @throws RequestException
     * @throws MapInvalidSourceException
     */
    public function updateLeaguesFromNinjaByVersion(PoeVersionDto $version): void
    {
        $ninjaLeagues = collect($this->ninjaClient->getEconomyLeagues($version->version));
        $currentLeagues = collect($this->poeLeagueRepository->getAllActive($version->id));

        DB::transaction(function () use ($ninjaLeagues, $currentLeagues, $version) {
            $this->poeLeagueRepository->upsert($this->constructUpsertData(
                $ninjaLeagues,
                $currentLeagues,
                $version,
            ));

            $this->poeLeagueRepository->upsertDefaultRulesForLeagues(
                collect($this->poeLeagueRepository->getAllActiveWithNoRule($version->id))->pluck('id')->toArray(),
                config('poe.price_check.default_hours_check_rule') * 60,
            );
        });

        Cache::tags([PoeLeagueTagEnum::league_version->name.$version->id])->flush();
    }

    private function constructUpsertData(
        Collection $ninjaLeagues,
        Collection $currentLeagues,
        PoeVersionDto $poeVersion,
    ): array {
        $leaguesToInsert = $ninjaLeagues->map(static function (PoeNinjaLeague $league) use ($poeVersion) {
            return [
                'league_name' => $league->name,
                'poe_version_id' => $poeVersion->id,
                'is_active' => true,
            ];
        });

        $ninjaNames = $leaguesToInsert->pluck('league_name');

        return $currentLeagues
            ->reject(static fn (PoeLeagueDto $league) => $ninjaNames->contains($league->league_name))
            ->map(static fn (PoeLeagueDto $league) => [
                'league_name' => $league->league_name,
                'poe_version_id' => $league->poe_version_id,
                'is_active' => false,
            ])
            ->merge($leaguesToInsert)
            ->toArray();
    }
}
