<?php

namespace App\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface PoeLeagueRepositoryContract
{
    /**
     * @return MainPoeLeagueDto[]
     */
    public function allMainLeaguesWithSparkline(string $exchangeItemTradeId, CarbonInterface $since): array;

    /**
     * @return ExtendedPoeLeagueDto[]
     */
    public function getWithLastPriceCheckAndRule(int $versionId): array;

    /**
     * @return PoeLeagueDto[]
     */
    public function getAllActiveWithNoRule(int $versionId): array;

    /**
     * @return PoeLeagueDto[]
     */
    public function getAllActive(int $versionId): array;

    /**
     * @return array<int, int>
     */
    public function getActiveIdsWithMetricsItems(): array;

    /**
     * @param  array<int, int>  $leaguesId
     */
    public function upsertDefaultRulesForLeagues(array $leaguesId, int $minutesCheck): void;

    /**
     * @throws ModelNotFoundException<Model> when the league does not exist
     */
    public function find(int $id): PoeLeagueDto;

    public function findActiveBySlug(string $slug): ?PoeLeagueDto;

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
    public function upsert(array $paramsWithValues): void;
}
