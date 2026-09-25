<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /** Type-ahead: name/code match, most-recently-used first. Empty query = MRU list. */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $rows = Location::active()
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->orderByDesc('last_used_at')->orderByDesc('usage_count')->orderBy('name')
            ->limit($q === '' ? 8 : 25)
            ->with('area:id,name')
            ->get(['id', 'name', 'code', 'easting', 'northing', 'area_id', 'verified', 'usage_count', 'last_used_at']);

        return response()->json($rows->map(fn ($l) => [
            'id' => $l->id, 'name' => $l->name, 'code' => $l->code,
            'easting' => $l->easting, 'northing' => $l->northing,
            'area' => $l->area?->name, 'verified' => $l->verified, 'usage_count' => $l->usage_count,
        ]));
    }

    public function nearby(Request $request)
    {
        $d = $request->validate(['easting' => 'required|numeric', 'northing' => 'required|numeric']);
        $near = Location::near($d['easting'], $d['northing'], config('hrwt.duplicate_radius_m'));
        $area = Area::containing($d['easting'], $d['northing']);

        return response()->json([
            'area' => $area ? ['id' => $area->id, 'name' => $area->name] : null,
            'nearby' => $near->values()->map(fn ($l) => [
                'id' => $l->id, 'name' => $l->name,
                'distance_m' => round(hypot($l->easting - $d['easting'], $l->northing - $d['northing']), 1),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => 'required|string|max:160|unique:locations,name',
            'code' => 'nullable|string|max:60',
            'easting' => 'required|numeric', 'northing' => 'required|numeric',
        ]);
        $loc = Location::create($d + [
            'area_id' => Area::containing($d['easting'], $d['northing'])?->id,
            'verified' => false,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($loc->load('area:id,name'), 201);
    }
}
