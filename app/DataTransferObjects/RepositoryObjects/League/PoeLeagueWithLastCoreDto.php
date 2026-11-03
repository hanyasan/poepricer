<?php

namespace App\DataTransferObjects\RepositoryObjects\League;

final readonly class PoeLeagueWithLastCoreDto
{
    public function __construct(
        public int $id,
        public string $league_name,
        public ?string $ninja_league_id,
        public bool $is_active,
        public int $poe_version_id,
        public ?string $last_core_created_at = null,
        public ?int $last_core_id = null,
        public ?LeagueCheckRuleDto $check_rule = null,
    ) {}
}
