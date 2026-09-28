<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Landmark;
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
        return response()->json(Landmark::where('active', true)->orderBy('name')
            ->get(['id', 'name', 'category', 'easting', 'northing']));
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
