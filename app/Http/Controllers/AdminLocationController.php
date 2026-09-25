<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Location;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminLocationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = Location::query()->with(['area:id,name', 'mergedInto:id,name']);

        if ($status === 'unverified') {
            $query->where('verified', false)->where('status', 'active');
        } elseif ($status === 'archived') {
            $query->where('status', 'archived');
        } elseif ($status === 'active') {
            $query->where('status', 'active');
        }

        if ($q !== '') {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
        }

        $locations = $query->orderByDesc('last_used_at')->orderBy('name')->paginate(30)->withQueryString();
        $allActive = Location::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.locations.index', [
            'locations' => $locations,
            'allActive' => $allActive,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function verify(Location $location)
    {
        $location->update(['verified' => true]);

        return back()->with('status', "Verified location '{$location->name}'.");
    }

    public function archive(Location $location)
    {
        $location->update(['status' => 'archived']);

        return back()->with('status', "Archived location '{$location->name}'.");
    }

    public function merge(Request $request, Location $location)
    {
        $data = $request->validate([
            'target_id' => 'required|exists:locations,id|different_location:' . $location->id,
        ]);

        $target = Location::findOrFail($data['target_id']);

        Entry::where('location_id', $location->id)->update([
            'location_id' => $target->id,
        ]);

        $location->update([
            'status' => 'archived',
            'merged_into_id' => $target->id,
        ]);

        $target->increment('usage_count', $location->usage_count);

        return back()->with('status', "Merged '{$location->name}' into '{$target->name}'.");
    }

    public function export(): StreamedResponse
    {
        $locations = Location::with('area:id,name')->orderBy('name')->get();
        $filename = 'locations-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use ($locations) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'code', 'easting', 'northing', 'area', 'status', 'verified', 'usage_count', 'last_used_at']);
            foreach ($locations as $l) {
                fputcsv($out, [
                    $l->id, $l->name, $l->code, $l->easting, $l->northing, $l->area?->name,
                    $l->status, $l->verified ? 1 : 0, $l->usage_count, $l->last_used_at,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) continue;
            [$id, $name, $code, $easting, $northing] = $row;
            if (empty(trim($name)) || !is_numeric($easting) || !is_numeric($northing)) continue;

            $area = Area::containing((float) $easting, (float) $northing);

            Location::updateOrCreate(
                ['name' => trim($name)],
                [
                    'code' => !empty($code) ? trim($code) : null,
                    'easting' => (float) $easting,
                    'northing' => (float) $northing,
                    'area_id' => $area?->id,
                    'verified' => true,
                    'status' => 'active',
                ]
            );
            $count++;
        }
        fclose($handle);

        return back()->with('status', "Imported/updated {$count} locations.");
    }
}
