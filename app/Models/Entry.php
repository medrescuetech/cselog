<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'easting' => 'float', 'northing' => 'float',
        'planned_start_at' => 'datetime', 'opened_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Entry $entry): void {
            if (! $entry->hrw_ref) {
                $entry->forceFill([
                    'hrw_ref' => 'HRW-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EntryEvent::class)->orderBy('occurred_at');
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open')->orderBy('opened_at');
    }

    public function elapsedSeconds(): int
    {
        return (int) $this->opened_at->diffInSeconds($this->closed_at ?? now());
    }

    public function ageBand(): string
    {
        if ($this->status !== 'open') {
            return 'none';
        }

        $h = $this->elapsedSeconds() / 3600;
        if ($h >= config('hwrt.red_hours')) {
            return 'red';
        }

        return $h >= config('hwrt.amber_hours') ? 'amber' : 'none';
    }

    public function log(string $event, ?array $changes = null): void
    {
        $this->events()->create([
            'event' => $event,
            'actor_id' => auth()->id(),
            'actor_ip' => request()?->ip(),
            'changes' => $changes,
            'occurred_at' => now(),
        ]);
    }
}
