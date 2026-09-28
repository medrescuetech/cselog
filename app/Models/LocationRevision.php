<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationRevision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
