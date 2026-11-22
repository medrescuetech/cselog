<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class MapSettingsController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'refresh_cadence' => 'required|in:manual,daily,weekly,monthly',
            'imagery_query' => 'required|string|max:160',
            'imagery_service_override' => 'nullable|url|max:2000',
            'colour_road' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colour_ga' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        Setting::put('map.refresh_cadence', $data['refresh_cadence']);
        Setting::put('map.imagery_query', $data['imagery_query']);
        Setting::put('map.imagery_service_override', $data['imagery_service_override'] ?? '');
        Setting::put('map.colour_road', $data['colour_road'] ?? '');
        Setting::put('map.colour_ga', $data['colour_ga'] ?? '');

        return back()->with('status', 'Map refresh settings updated.');
    }

    public function requestRefresh()
    {
        Setting::put('map.refresh_requested_at', now()->toIso8601String());
        Setting::put('map.last_status', 'queued');

        return back()->with('status', 'Map refresh requested. The scheduler will process it shortly.');
    }
}
