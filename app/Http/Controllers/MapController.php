<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Landmark;
use App\Models\MapSector;
use App\Models\Setting;
use Illuminate\Support\Facades\File;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index', ['manifest' => $this->manifest()]);
    }

    /** The sitemap manifest with file paths resolved to URLs. */
    public function layers()
    {
        return response()->json($this->manifest());
    }

    public function areas()
    {
        return response()->json([
            'type' => 'FeatureCollection',
            'crs_epsg' => 28350,
            'features' => Area::where('active', true)->orderBy('sort_order')->get()->map(fn ($a) => [
                'type' => 'Feature',
                'id' => $a->id,
                'properties' => ['name' => $a->name, 'kind' => $a->kind, 'colour' => $a->colour, 'fill_opacity' => $a->fill_opacity],
                'geometry' => $a->geometry,
            ]),
        ]);
    }

    public function landmarks()
    {
        return response()->json(Landmark::where('active', true)->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'category', 'marker_type', 'colour', 'label_visible', 'rotation_deg', 'notes', 'easting', 'northing']));
    }

    public function sectors()
    {
        return response()->json([
            'type' => 'FeatureCollection',
            'crs_epsg' => 28350,
            'features' => MapSector::where('active', true)->orderBy('sort_order')->orderBy('name')->get()->map(fn ($sector) => [
                'type' => 'Feature',
                'id' => $sector->id,
                'properties' => [
                    'name' => $sector->name,
                    'colour' => $sector->colour,
                    'fill_opacity' => $sector->fill_opacity,
                    'notes' => $sector->notes,
                ],
                'geometry' => $sector->geometry,
            ]),
        ]);
    }

    private function manifest(): array
    {
        $path = rtrim(config('hwrt.sitemap_path'), '/').'/manifest.json';
        $m = File::exists($path) ? json_decode(File::get($path), true) : ['rasters' => [], 'vectors' => [], 'extent' => null];
        $base = rtrim(config('hwrt.sitemap_url'), '/');
        foreach ($m['rasters'] as &$r) {
            $r['url'] = $base.'/'.$r['file'];
            unset($r['source']);
        }
        foreach ($m['vectors'] ?? [] as &$v) {
            $v['url'] = $base.'/'.$v['file'];
            unset($v['source']);
        }
        $m['default_view'] = $this->defaultView();

        return $m;
    }

    private function defaultView(): ?array
    {
        $view = json_decode((string) Setting::value('map.default_view', ''), true);
        if (! is_array($view)) return null;

        $easting = filter_var($view['easting'] ?? null, FILTER_VALIDATE_FLOAT);
        $northing = filter_var($view['northing'] ?? null, FILTER_VALIDATE_FLOAT);
        $zoom = filter_var($view['zoom'] ?? null, FILTER_VALIDATE_FLOAT);

        return $easting === false || $northing === false || $zoom === false
            ? null
            : ['easting' => $easting, 'northing' => $northing, 'zoom' => $zoom];
    }
}
