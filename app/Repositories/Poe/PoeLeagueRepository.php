<?php

namespace App\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueWithLastCoreDto;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague as PoeLeagueModel;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class PoeLeagueRepository implements PoeLeagueRepositoryContract
{
    public function __construct(
        private PoeLeagueModel $model,
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @param  array<int, int>  $leaguesId
     */
    #[\Override]
    public function upsertDefaultRulesForLeagues(array $leaguesId, int $minutesCheck): void
    {
        $defaultRules = array_map(static function (int $id) use ($minutesCheck) {
            return [
                'poe_league_id' => $id,
                'minutes_check' => $minutesCheck,
            ];
        }, $leaguesId);

        LeagueCheckRule::query()->upsert(
            $defaultRules,
            ['poe_league_id'],
            ['minutes_check'],
        );
    }

    /**
     * @return PoeLeagueDto[]
     */
    #[\Override]
    public function getAllActiveWithRule(int $versionId): array
    {
        return $this->mapper->mapEloquentCollection(
            PoeLeagueDto::class,
            $this->model->newQuery()
                ->with('check_rule')
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->get(),
        );
    }

    /**
     * @return PoeLeagueDto[]
     */
    #[\Override]
    public function getAllActiveWithNoRule(int $versionId): array
    {
        return $this->mapper->mapEloquentCollection(
            PoeLeagueDto::class,
            $this->model->newQuery()
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->whereDoesntHave('check_rule')
                ->get(),
        );
    }

    /**
     * @return PoeLeagueDto[]
     */
    #[\Override]
    public function getAllActive(int $versionId): array
    {
        return $this->mapper->mapEloquentCollection(
            PoeLeagueDto::class,
            $this->model->newQuery()
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->get(),
        );
    }

    #[\Override]
    public function find(int $id): PoeLeagueDto
    {
        /** @var PoeLeagueDto $dto */
        $dto = $this->mapper->mapEloquentModel(
            PoeLeagueDto::class,
            $this->model->newQuery()->with('check_rule')->findOrFail($id),
        );

        return $dto;
    }

    #[\Override]
    public function findActiveByNinjaIdVersion(string $ninjaLeagueId, int $versionId): PoeLeagueDto
    {
        /** @var PoeLeagueDto $dto */
        $dto = $this->mapper->mapEloquentModel(
            PoeLeagueDto::class,
            $this->model->newQuery()
                ->with('check_rule')
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->where('ninja_league_id', $ninjaLeagueId)
                ->firstOrFail(),
        );

        return $dto;
    }

    /**
     * @param  array<int, array{
     *     league_name: string,
     *     ninja_league_id: string,
     *     poe_version_id: int,
     *     is_active: bool,
     * }>  $paramsWithValues
     */
    #[\Override]
    public function upsert(array $paramsWithValues): void
    {
        $this->model->newQuery()->upsert(
            $paramsWithValues,
            ['ninja_league_id', 'poe_version_id'],
            ['league_name', 'is_active'],
        );
    }
}
