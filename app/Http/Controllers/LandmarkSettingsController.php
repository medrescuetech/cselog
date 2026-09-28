<?php

namespace App\Http\Controllers;

use App\Models\Landmark;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandmarkSettingsController extends Controller
{
    public function index()
    {
        return view('settings.landmarks', [
            'landmarks' => Landmark::query()->orderByDesc('active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['active'] = (bool) $data['active'];
        $data['source'] = 'manual';
        Landmark::create($data);

        return back()->with('status', 'Landmark added.');
    }

    public function update(Request $request, Landmark $landmark)
    {
        $data = $this->validated($request, $landmark);
        $data['active'] = (bool) $data['active'];
        if ($landmark->source === null) {
            $data['source'] = 'manual';
        }
        $landmark->update($data);

        return back()->with('status', 'Landmark updated.');
    }

    private function validated(Request $request, ?Landmark $landmark = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('landmarks', 'name')
                    ->where(fn ($q) => $q->where('category', $request->input('category')))
                    ->ignore($landmark?->id),
            ],
            'category' => 'nullable|string|max:60',
            'easting' => 'required|numeric',
            'northing' => 'required|numeric',
            'active' => 'required|boolean',
        ]);
    }
}
