<?php

namespace App\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\Exceptions\Mapping\MappingErrorException;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Utility\Mapper\MapperServiceContract;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class PoeLeagueRepository implements PoeLeagueRepositoryContract
{
    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return MainPoeLeagueDto[]
     *
     * @throws MappingErrorException
     */
    #[\Override]
    public function allMainLeaguesWithSparkline(string $exchangeItemTradeId, CarbonInterface $since): array
    {
        $currencySeries = static function (Builder $query) use ($exchangeItemTradeId, $since): void {
            $query
                ->where('bucket_start', '>=', $since)
                ->whereHas('exchangeMetricsItem.exchangeItem', function (Builder $query) use ($exchangeItemTradeId) {
                    $query->where('trade_id', $exchangeItemTradeId);
                });
        };

        return $this->mapper->mapEloquentCollection(
            MainPoeLeagueDto::class,
            PoeLeague::query()
                ->with([
                    'poeVersion',
                    'latestPriceCheck',
                    'exchangeMarketMetrics' => static function (Relation $relation) use ($currencySeries) {
                        $query = $relation->getQuery();
                        $currencySeries($query);
                        $query->orderBy('bucket_start');
                    },
                ])
                ->whereHas('exchangeMarketMetrics', $currencySeries)
                ->where('is_active', true)
                ->orderBy(
                    PoeVersion::query()->select('version')->whereColumn('poe_versions.id', 'poe_leagues.poe_version_id'),
                )
                ->orderBy('poe_leagues.id')
                ->get()
        );
    }

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
     *     slug: string,
     *     started_at: string|null,
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
