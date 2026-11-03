<?php

namespace App\Models\Poe\Exchange;

use App\Models\Poe\PoeLeague;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $created_at
 * @property-read int $poe_league_id
 * @property-read PoeLeague $league
 */
final class PriceCheck extends Model
{
    protected $table = 'price_checks';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function league(): BelongsTo
    {
        return $this->belongsTo(PoeLeague::class, 'poe_league_id');
    }
}
