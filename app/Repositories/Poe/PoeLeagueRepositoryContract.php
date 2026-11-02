<?php

namespace App\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueWithLastCoreDto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface PoeLeagueRepositoryContract
{
    /**
     * @return PoeLeagueDto[]
     */
    public function getAllActive(): array;

    /**
     * @param  array<int, int>  $leaguesId
     */
    public function upsertDefaultRulesForLeagues(array $leaguesId): void;

    /**
     * @throws ModelNotFoundException<Model> when the league does not exist
     */
    public function find(int $id): PoeLeagueDto;

    /**
     * @throws ModelNotFoundException<Model> when the league does not exist
     */
    public function findActiveByNameVersion(string $name, int $versionId): PoeLeagueDto;

    /**
     * @param  array<int, array{
     *     league_name: string,
     *     poe_version_id: int,
     *     is_active: bool,
     * }>  $paramsWithValues
     */
    public function upsert(array $paramsWithValues): void;

    /**
     * @return PoeLeagueWithLastCoreDto[]
     */
    public function getActiveWithLatestCore(): array;
}
