<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Landmark;
use App\Models\MapSector;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

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
            ->get(['id', 'name', 'category', 'marker_type', 'colour', 'label_visible', 'rotation_deg', 'notes', 'easting', 'northing', 'source']));
    }

    /** Quick-add a point of interest (ERT pickup, AED, muster point, obstruction, …). */
    public function storePoi(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('landmarks', 'name')
                    ->where(fn ($q) => $q->where('marker_type', $request->input('marker_type'))),
            ],
            'marker_type' => ['required', Rule::in(array_keys(Landmark::POI_TYPES))],
            'easting' => 'required|numeric',
            'northing' => 'required|numeric',
        ]);

        $lm = Landmark::create([
            ...$data,
            'category' => 'poi',
            'colour' => Landmark::POI_TYPES[$data['marker_type']][1],
            'label_visible' => false,
            'rotation_deg' => 0,
            'source' => 'user',
            'active' => true,
        ]);

        return response()->json($lm->only([
            'id', 'name', 'category', 'marker_type', 'colour', 'label_visible',
            'rotation_deg', 'notes', 'easting', 'northing', 'source',
        ]), 201);
    }

    public function destroyPoi(Landmark $landmark)
    {
        // Only user-placed points can be removed here; imported/manual data is managed via settings.
        abort_unless($landmark->source === 'user', 403);
        $landmark->delete();

        return response()->json(['deleted' => true]);
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
        $root = rtrim(config('hwrt.sitemap_path'), '/');
        $path = $root.'/manifest.json';
        $m = File::exists($path) ? json_decode(File::get($path), true) : ['rasters' => [], 'vectors' => [], 'extent' => null];
        $base = rtrim(config('hwrt.sitemap_url'), '/');
        // Map refreshes replace files under the same name; version the URL so browser/CDN
        // caches can never pair stale image bytes with a newer extent.
        $url = function (string $file) use ($root, $base): string {
            $full = $root.'/'.$file;
            $v = is_file($full) ? substr(md5(filemtime($full).':'.filesize($full)), 0, 10) : null;

            return $base.'/'.$file.($v ? '?v='.$v : '');
        };
        foreach ($m['rasters'] as &$r) {
            $r['url'] = $url($r['file']);
            unset($r['source']);
        }
        unset($r);
        foreach ($m['vectors'] ?? [] as &$v) {
            $v['url'] = $url($v['file']);
            unset($v['source']);
        }
        unset($v);
        $m['default_view'] = $this->defaultView();
        // Optional layer colour overrides from Settings → Map (blank = use feature colour).
        $m['styling'] = [
            'road' => Setting::value('map.colour_road', ''),
            'ga' => Setting::value('map.colour_ga', ''),
        ];

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
