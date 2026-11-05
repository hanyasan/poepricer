<?php

namespace App\Models\Poe\Exchange\Compact;

use App\Models\Poe\PoeLeague;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property string $date
 * @property-read int $poe_league_id
 * @property-read PoeLeague $league
 * @property-read Collection<int, CompactPriceCheckCore> $cores
 */
final class CompactPriceCheck extends Model
{
    protected $table = 'compact_price_checks';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function league(): BelongsTo
    {
        return $this->belongsTo(PoeLeague::class, 'poe_league_id');
    }

    public function cores(): HasMany
    {
        return $this->hasMany(CompactPriceCheckCore::class, 'compact_price_check_id');
    }
}
