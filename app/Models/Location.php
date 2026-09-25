<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $guarded = [];

    protected $casts = [
        'easting' => 'float', 'northing' => 'float', 'verified' => 'bool', 'last_used_at' => 'datetime',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'merged_into_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active')->whereNull('merged_into_id');
    }

    /** Active locations within $metres of a point, nearest first. */
    public static function near(float $e, float $n, float $metres = 15)
    {
        return static::active()
            ->whereBetween('easting', [$e - $metres, $e + $metres])
            ->whereBetween('northing', [$n - $metres, $n + $metres])
            ->get()
            ->filter(fn ($l) => hypot($l->easting - $e, $l->northing - $n) <= $metres)
            ->sortBy(fn ($l) => hypot($l->easting - $e, $l->northing - $n));
    }
}
