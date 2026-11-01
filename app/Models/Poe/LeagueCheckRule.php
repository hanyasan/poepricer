<?php

namespace App\Models\Poe;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property int $poe_league_id
 * @property-read PoeLeague $league
 * @property int|null $minutes_check
 */
final class LeagueCheckRule extends Model
{
    protected $table = 'league_check_rules';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function league(): BelongsTo
    {
        return $this->belongsTo(PoeLeague::class, 'poe_league_id');
    }
}
