<?php

namespace App\Models\Poe\Exchange;

use App\Models\Poe\PoeVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property string $category
 * @property string $value
 * @property-read int $poe_version_id
 * @property-read PoeVersion $poeVersion
 */
final class ExchangeType extends Model
{
    protected $table = 'exchange_types';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function poeVersion(): BelongsTo
    {
        return $this->belongsTo(PoeVersion::class, 'poe_version_id');
    }
}
