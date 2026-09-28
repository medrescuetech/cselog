<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $guarded = [];

    protected $casts = [
        'easting' => 'float', 'northing' => 'float', 'verified' => 'bool', 'last_used_at' => 'datetime',
        'document_uploaded_at' => 'datetime',
        'aliases' => 'array',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active')->whereNull('merged_into_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LocationRevision::class)->orderByDesc('id');
    }

    public function recordRevision(string $action, ?array $before = null, ?string $reason = null): void
    {
        $this->revisions()->create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'before' => $before,
            'after' => $this->only(['name', 'code', 'aliases', 'easting', 'northing', 'area_id', 'status', 'verified']),
            'reason' => $reason,
        ]);
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
