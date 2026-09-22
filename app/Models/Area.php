<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $guarded = [];

    protected $casts = ['geometry' => 'array', 'active' => 'bool', 'fill_opacity' => 'float'];

    /** Smallest active area containing the point (so a building beats the site envelope). */
    public static function containing(float $e, float $n): ?self
    {
        $best = null;
        $bestArea = INF;
        foreach (static::where('active', true)->get() as $area) {
            if (Geo::contains($area->geometry, $e, $n)) {
                $size = Geo::area($area->geometry);
                if ($size < $bestArea) {
                    $best = $area;
                    $bestArea = $size;
                }
            }
        }

        return $best;
    }
}
