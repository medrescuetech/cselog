<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkType extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'bool', 'requires_note' => 'bool', 'active' => 'bool'];

    public function scopeActive($q)
    {
        return $q->where('active', true)->orderBy('sort_order')->orderBy('name');
    }
}
