<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SettingsController extends Controller
{
    public function index()
    {
        $manifestPath = rtrim(config('hwrt.sitemap_path'), '/').'/manifest.json';
        $manifest = File::exists($manifestPath)
            ? json_decode(File::get($manifestPath), true)
            : null;

        return view('settings.index', [
            'theme' => Setting::value('appearance.theme', 'dark'),
            'version' => config('hwrt.version'),
            'manifest' => $manifest,
            'manifestPath' => $manifestPath,
        ]);
    }

    public function appearance(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:dark,light',
        ]);

        Setting::put('appearance.theme', $data['theme']);

        return back()->with('status', 'Appearance updated.');
    }
}
