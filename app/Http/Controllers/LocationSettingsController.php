<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LocationSettingsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return view('settings.locations', [
            'locations' => Location::query()
                ->with('area:id,name')
                ->when($q !== '', fn ($b) => $b->where('name', 'like', "%{$q}%"))
                ->orderBy('name')
                ->paginate(100)
                ->withQueryString(),
            'q' => $q,
        ]);
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
