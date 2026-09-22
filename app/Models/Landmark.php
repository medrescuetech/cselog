<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Landmark extends Model
{
    protected $guarded = [];

    protected $casts = ['easting' => 'float', 'northing' => 'float', 'active' => 'bool'];
}
