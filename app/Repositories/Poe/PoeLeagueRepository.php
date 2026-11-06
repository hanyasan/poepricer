<?php

namespace App\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague;
use App\Utility\Mapper\MapperServiceContract;

final readonly class PoeLeagueRepository implements PoeLeagueRepositoryContract
{
    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return ExtendedPoeLeagueDto[]
     */
    #[\Override]
    public function getWithLastPriceCheckAndRule(int $versionId): array
    {
        return $this->mapper->mapEloquentCollection(
            ExtendedPoeLeagueDto::class,
            PoeLeague::query()
                ->with([
                    'checkRule',
                    'latestPriceCheck',
                    'poeVersion',
                ])
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->get(),
        );
    }

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
    public function getAllActiveWithNoRule(int $versionId): array
    {
        return $this->mapper->mapEloquentCollection(
            PoeLeagueDto::class,
            PoeLeague::query()
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->whereDoesntHave('checkRule')
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
            PoeLeague::query()
                ->where('is_active', true)
                ->where('poe_version_id', $versionId)
                ->get(),
        );
    }

    /**
     * @return array<int, int>
     */
    #[\Override]
    public function getActiveIdsWithMetricsItems(): array
    {
        return PoeLeague::query()
            ->where('is_active', true)
            ->whereIn('id', ExchangeMetricsItem::query()->select('poe_league_id'))
            ->pluck('id')
            ->all();
    }

    #[\Override]
    public function find(int $id): PoeLeagueDto
    {
        /** @var PoeLeagueDto $dto */
        $dto = $this->mapper->mapEloquentModel(
            PoeLeagueDto::class,
            PoeLeague::query()->with('checkRule')->findOrFail($id),
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
        PoeLeague::query()->upsert(
            $paramsWithValues,
            ['ninja_league_id', 'poe_version_id'],
            ['league_name', 'is_active'],
        );
    }
}
