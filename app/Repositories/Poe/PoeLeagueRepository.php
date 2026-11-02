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
    public function upsertDefaultRulesForLeagues(array $leaguesId): void
    {
        $defaultRules = array_map(static function (int $id) {
            return [
                'poe_league_id' => $id,
                'minutes_check' => config('price_check.default_hours_check') * 60,
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
    public function getAllActive(): array
    {
        return $this->mapper->mapEloquentCollection(
            PoeLeagueDto::class,
            $this->model->newQuery()->with('check_rule')->where('is_active', true)->get(),
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
    public function findActiveByNameVersion(string $name, int $versionId): PoeLeagueDto
    {
        /** @var PoeLeagueDto $dto */
        $dto = $this->mapper->mapEloquentModel(
            PoeLeagueDto::class,
            $this->model->newQuery()
                ->with('check_rule')
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->where('league_name', $name)
                ->firstOrFail(),
        );

        return $dto;
    }

    /**
     * @param  array<int, array{
     *     league_name: string,
     *     poe_version_id: int,
     *     is_active: bool,
     * }>  $paramsWithValues
     */
    #[\Override]
    public function upsert(array $paramsWithValues): void
    {
        $this->model->newQuery()->upsert(
            $paramsWithValues,
            ['league_name', 'poe_version_id'],
            ['is_active'],
        );
    }

    /**
     * @return PoeLeagueWithLastCoreDto[]
     */
    #[\Override]
    public function getActiveWithLatestCore(): array
    {
        $latestCoreDates = PriceCheckCore::query()
            ->select([
                'price_check_cores.poe_league_id as last_core_poe_league_id',
                DB::raw('MAX(created_at) as last_core_created_at'),
            ])
            ->groupBy('last_core_poe_league_id');

        $latestCores = PriceCheckCore::query()
            ->select([
                'price_check_cores.poe_league_id',
                'price_check_cores.created_at as last_core_created_at',
                'price_check_cores.id as last_core_id',
            ])
            ->joinSub($latestCoreDates, 'latest_core_dates', function (JoinClause $join) {
                $join->on('latest_core_dates.last_core_poe_league_id', '=', 'price_check_cores.poe_league_id')
                    ->whereRaw(DB::raw('latest_core_dates.last_core_created_at = price_check_cores.created_at'));
            });

        return $this->mapper->mapEloquentCollection(
            PoeLeagueWithLastCoreDto::class,
            $this->model->newQuery()
                ->with('check_rule')
                ->leftJoinSub($latestCores, 'latest_cores', function (JoinClause $join) {
                    $join->on('poe_leagues.id', '=', 'latest_cores.poe_league_id');
                })
                ->where('is_active', true)
                ->get(),
        );
    }
}
