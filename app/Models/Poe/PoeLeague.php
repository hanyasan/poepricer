<?php

namespace App\Models\Poe;

use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\PriceCheck;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int $id
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read null|LeagueCheckRule $check_rule
 * @property string $league_name
 * @property string $ninja_league_id
 * @property string $slug
 * @property bool $is_active
 * @property string|null $started_at
 * @property-read int $poe_version_id
 * @property-read PoeVersion $poeVersion
 */
final class PoeLeague extends Model
{
    protected $table = 'poe_leagues';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function checkRule(): HasOne
    {
        return $this->hasOne(LeagueCheckRule::class, 'poe_league_id');
    }

    public function poeVersion(): BelongsTo
    {
        return $this->belongsTo(PoeVersion::class, 'poe_version_id');
    }

    public function priceChecks(): HasMany
    {
        return $this->hasMany(PriceCheck::class, 'poe_league_id');
    }

    public function latestPriceCheck(): HasMany
    {
        return $this->priceChecks()->latest()->limit(1);
    }

    public function exchangeMarketMetrics(): HasMany
    {
        return $this->hasMany(ExchangeMarketMetric::class, 'poe_league_id');
    }

    public function latestExchangeMarketMetrics(): HasMany
    {
        return $this->exchangeMarketMetrics()->latest('bucket_start')->limit(1);
    }
}
