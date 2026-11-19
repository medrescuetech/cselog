<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapSector extends Model
{
    protected $guarded = [];

    protected $casts = [
        'geometry' => 'array',
        'fill_opacity' => 'float',
        'active' => 'bool',
    ];
}
