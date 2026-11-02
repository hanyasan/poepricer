<?php

namespace App\Models\Poe;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property string $version
 */
final class PoeVersion extends Model
{
    protected $table = 'poe_versions';

    protected $guarded = ['id'];

    public $timestamps = false;
}
