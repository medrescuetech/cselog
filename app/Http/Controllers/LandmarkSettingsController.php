<?php

namespace App\Http\Controllers;

use App\Models\Landmark;
use App\Models\MapSector;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LandmarkSettingsController extends Controller
{
    public function index()
    {
        return view('settings.landmarks', [
            'landmarks' => Landmark::query()->orderByDesc('active')->orderBy('category')->orderBy('name')->get(),
            'sectors' => MapSector::query()->orderByDesc('active')->orderBy('sort_order')->orderBy('name')->get(),
            'markerTypes' => Landmark::MARKER_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['active'] = (bool) $data['active'];
        $data['source'] = 'manual';
        Landmark::create($data);

        return back()->with('status', 'Map marker added.');
    }

    public function update(Request $request, Landmark $landmark)
    {
        $data = $this->validated($request, $landmark);
        $data['active'] = (bool) $data['active'];
        if ($landmark->source === null) {
            $data['source'] = 'manual';
        }
        $landmark->update($data);

        return back()->with('status', 'Map marker updated.');
    }

    public function storeSector(Request $request)
    {
        MapSector::create($this->validatedSector($request));

        return back()->with('status', 'Map sector added.');
    }

    public function updateSector(Request $request, MapSector $mapSector)
    {
        $mapSector->update($this->validatedSector($request, $mapSector));

        return back()->with('status', 'Map sector updated.');
    }

    private function validated(Request $request, ?Landmark $landmark = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('landmarks', 'name')
                    ->where(fn ($q) => $q->where('category', $request->input('category')))
                    ->ignore($landmark?->id),
            ],
            'category' => 'nullable|string|max:60',
            'marker_type' => ['required', Rule::in(array_keys(Landmark::MARKER_TYPES))],
            'colour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'easting' => 'required|numeric',
            'northing' => 'required|numeric',
            'active' => 'required|boolean',
            'label_visible' => 'required|boolean',
            'rotation_deg' => 'nullable|integer|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ]);
        $data['rotation_deg'] = (int) ($data['rotation_deg'] ?? 0);

        return $data;
    }

    private function validatedSector(Request $request, ?MapSector $mapSector = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('map_sectors', 'name')->ignore($mapSector?->id),
            ],
            'colour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fill_opacity' => 'required|numeric|between:0,1',
            'geometry' => 'required|string',
            'notes' => 'nullable|string|max:1000',
            'active' => 'required|boolean',
            'sort_order' => 'required|integer|between:-999,999',
        ]);

        $geometry = json_decode($data['geometry'], true);
        if (! $this->validGeometry($geometry)) {
            throw ValidationException::withMessages([
                'geometry' => 'Geometry must be a valid GeoJSON Polygon or MultiPolygon using numeric easting/northing coordinates.',
            ]);
        }
        $data['geometry'] = $geometry;
        $data['active'] = (bool) $data['active'];

        return $data;
    }

    private function validGeometry(mixed $geometry): bool
    {
        if (! is_array($geometry) || ! is_array($geometry['coordinates'] ?? null)) {
            return false;
        }

        return match ($geometry['type'] ?? null) {
            'Polygon' => $this->validPolygon($geometry['coordinates']),
            'MultiPolygon' => $geometry['coordinates'] !== []
                && count(array_filter($geometry['coordinates'], fn ($polygon) => $this->validPolygon($polygon))) === count($geometry['coordinates']),
            default => false,
        };
    }

    private function validPolygon(mixed $polygon): bool
    {
        if (! is_array($polygon) || $polygon === []) {
            return false;
        }
        foreach ($polygon as $ring) {
            if (! is_array($ring) || count($ring) < 4 || $ring[0] !== $ring[array_key_last($ring)]) {
                return false;
            }
            foreach ($ring as $position) {
                if (! is_array($position) || ! is_numeric($position[0] ?? null) || ! is_numeric($position[1] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }
}
