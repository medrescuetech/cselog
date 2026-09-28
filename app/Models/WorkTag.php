<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkTag extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'bool'];
}
