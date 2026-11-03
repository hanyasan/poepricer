<?php

namespace App\Models\Poe;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int $id
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read null|LeagueCheckRule $check_rule
 * @property string $league_name
 * @property string|null $ninja_league_id
 * @property bool $is_active
 * @property-read int $poe_version_id
 * @property-read PoeVersion $poeVersion
 */
final class PoeLeague extends Model
{
    protected $table = 'poe_leagues';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function check_rule(): HasOne
    {
        return $this->hasOne(LeagueCheckRule::class, 'poe_league_id');
    }

    public function poeVersion(): BelongsTo
    {
        return $this->belongsTo(PoeVersion::class, 'poe_version_id');
    }
}
