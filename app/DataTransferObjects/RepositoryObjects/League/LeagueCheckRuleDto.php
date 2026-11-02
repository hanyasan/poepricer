<?php

namespace App\DataTransferObjects\RepositoryObjects\League;

final readonly class LeagueCheckRuleDto
{
    public function __construct(
        public int $id,
        public ?int $minutes_check,
        public int $poe_league_id,
    ) {}
}
