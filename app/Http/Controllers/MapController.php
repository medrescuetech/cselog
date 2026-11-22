<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Landmark;
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
        return response()->json(Landmark::where('active', true)->orderBy('name')
            ->get(['id', 'name', 'category', 'easting', 'northing', 'source']));
    }

    /** Quick-add a point of interest (ERT pickup, AED, muster point, obstruction, …). */
    public function storePoi(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('landmarks', 'name')
                    ->where(fn ($q) => $q->where('category', $request->input('category'))),
            ],
            'category' => 'required|string|max:60',
            'easting' => 'required|numeric',
            'northing' => 'required|numeric',
        ]);

        $lm = Landmark::create([...$data, 'source' => 'user', 'active' => true]);

        return response()->json($lm->only(['id', 'name', 'category', 'easting', 'northing', 'source']), 201);
    }

    public function destroyPoi(Landmark $landmark)
    {
        // Only user-placed points can be removed here; imported data is managed via settings/refresh.
        abort_unless(in_array($landmark->source, ['user', 'manual']), 403);
        $landmark->delete();

        return response()->json(['deleted' => true]);
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

        return $m;
    }
}
