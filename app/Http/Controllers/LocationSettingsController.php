<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LocationSettingsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $unverified = $request->boolean('unverified');

        return view('settings.locations', [
            'locations' => Location::query()
                ->with('area:id,name')
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")->orWhere('aliases', 'like', "%{$q}%")))
                ->when($unverified, fn ($b) => $b->where('verified', false))
                ->orderBy('name')
                ->paginate(100)
                ->withQueryString(),
            'q' => $q,
            'unverified' => $unverified,
        ]);
    }

    public function create()
    {
        return view('settings.locations.form', ['location' => null, 'revisions' => collect(), 'openJobs' => 0]);
    }

    public function edit(Location $location)
    {
        return view('settings.locations.form', [
            'location' => $location,
            'revisions' => $location->revisions()->with('actor:id,name')->limit(25)->get(),
            'openJobs' => Entry::query()->where('location_id', $location->id)
                ->whereIn('status', ['open', 'pending'])->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedLocation($request);
        $location = DB::transaction(function () use ($request, $data) {
            $location = Location::create($data + ['verified' => true, 'created_by' => $request->user()->id]);
            $location->recordRevision('pre-stored');

            return $location;
        });

        return redirect()->route('settings.locations.edit', $location)->with('status', 'Location saved. It is available in the work picker.');
    }

    public function update(Request $request, Location $location)
    {
        $data = $this->validatedLocation($request, $location);
        $moving = (float) $location->easting !== (float) $data['easting']
            || (float) $location->northing !== (float) $data['northing'];
        $reason = trim((string) $request->input('reason'));
        if ($moving && $reason === '') {
            return back()->withErrors(['reason' => 'Explain why this saved location is moving.'])->withInput();
        }

        DB::transaction(function () use ($location, $data, $moving, $reason) {
            $before = $location->only(['name', 'code', 'aliases', 'easting', 'northing', 'area_id', 'status', 'verified']);
            $location->update($data);
            if ($location->wasChanged()) {
                $location->recordRevision($moving ? 'moved' : 'edited', $before, $reason ?: null);
            }
        });

        return redirect()->route('settings.locations.edit', $location)->with('status', 'Catalogue location updated. Existing work records retain their original location and coordinates.');
    }

    public function status(Request $request, Location $location)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'archived'])],
            'reason' => 'required|string|max:255',
        ]);
        if ($location->status !== $data['status']) {
            DB::transaction(function () use ($location, $data) {
                $before = $location->only(['name', 'code', 'aliases', 'easting', 'northing', 'area_id', 'status', 'verified']);
                $location->update(['status' => $data['status']]);
                $location->recordRevision($data['status'], $before, $data['reason']);
            });
        }

        return redirect()->route('settings.locations.edit', $location)->with('status', 'Location status updated; historical jobs are unchanged.');
    }

    private function validatedLocation(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('locations', 'name')->ignore($location?->id)],
            'code' => 'nullable|string|max:60',
            'aliases_text' => 'nullable|string|max:500',
            'easting' => 'required|numeric|between:0,999999.999',
            'northing' => 'required|numeric|between:0,99999999.999',
            'reason' => 'nullable|string|max:255',
        ]);
        $data['code'] = trim((string) ($data['code'] ?? '')) ?: null;
        $data['aliases'] = array_values(array_unique(array_filter(array_map('trim',
            preg_split('/[,\r\n]+/', $data['aliases_text'] ?? '') ?: []))));
        $data['area_id'] = Area::containing((float) $data['easting'], (float) $data['northing'])?->id;
        unset($data['aliases_text'], $data['reason']);

        return $data;
    }

    public function verify(Request $request, Location $location)
    {
        $data = $request->validate(['verified' => 'required|boolean']);

        if ($location->verified !== (bool) $data['verified']) {
            $before = $location->only(['name', 'code', 'aliases', 'easting', 'northing', 'area_id', 'status', 'verified']);
            DB::transaction(function () use ($location, $data, $before) {
                $location->update(['verified' => (bool) $data['verified']]);
                $location->recordRevision($location->verified ? 'verified' : 'unverified', $before);
            });
        }

        $state = $location->verified ? 'verified' : 'marked unverified';

        return back()->with('status', "{$location->name} {$state}.");
    }

    public function upload(Request $request, Location $location)
    {
        $data = $request->validate([
            'document' => 'required|file|mimes:pdf|max:25600',
        ]);

        if ($location->document_path) {
            Storage::disk('local')->delete($location->document_path);
        }

        $file = $data['document'];
        $path = $file->storeAs(
            'location-documents/'.$location->id,
            'location-'.$location->id.'-'.now()->format('YmdHis').'.pdf',
            'local',
        );

        $location->update([
            'document_path' => $path,
            'document_name' => $file->getClientOriginalName(),
            'document_uploaded_at' => now(),
            'document_uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', "PDF attached to {$location->name}.");
    }

    public function remove(Location $location)
    {
        if ($location->document_path) {
            Storage::disk('local')->delete($location->document_path);
        }

        $location->update([
            'document_path' => null,
            'document_name' => null,
            'document_uploaded_at' => null,
            'document_uploaded_by' => null,
        ]);

        return back()->with('status', "PDF removed from {$location->name}.");
    }

    public function download(Location $location)
    {
        abort_unless($location->document_path && Storage::disk('local')->exists($location->document_path), 404);

        return Storage::disk('local')->download(
            $location->document_path,
            $location->document_name ?: 'location-'.$location->id.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
