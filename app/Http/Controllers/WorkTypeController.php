<?php

namespace App\Http\Controllers;

use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkTypeController extends Controller
{
    public function index()
    {
        return view('settings.work-types', [
            'workTypes' => WorkType::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['active'] = (bool) $data['active'];
        $data['requires_note'] = (bool) $data['requires_note'];
        $data['is_default'] = (bool) $data['is_default'];
        $data['is_other'] = (bool) $data['is_other'];

        if ($data['is_default']) {
            WorkType::query()->update(['is_default' => false]);
        }
        if ($data['is_other']) {
            WorkType::query()->update(['is_other' => false]);
            $data['requires_note'] = true;
        }

        WorkType::create($data);

        return back()->with('status', 'High risk work type created.');
    }

    public function update(Request $request, WorkType $workType)
    {
        $data = $this->validated($request, $workType);
        $data['active'] = (bool) $data['active'];
        $data['requires_note'] = (bool) $data['requires_note'];
        $data['is_default'] = (bool) $data['is_default'];
        $data['is_other'] = (bool) $data['is_other'];

        if ($data['is_default']) {
            WorkType::query()->whereKeyNot($workType->id)->update(['is_default' => false]);
        }
        if ($data['is_other']) {
            WorkType::query()->whereKeyNot($workType->id)->update(['is_other' => false]);
            $data['requires_note'] = true;
        }

        $workType->update($data);

        return back()->with('status', 'High risk work type updated.');
    }

    private function validated(Request $request, ?WorkType $workType = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('work_types', 'name')->ignore($workType?->id),
            ],
            'colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => 'required|integer|min:0|max:9999',
            'notes_prompt' => 'nullable|string|max:255',
            'active' => 'required|boolean',
            'requires_note' => 'required|boolean',
            'is_default' => 'required|boolean',
            'is_other' => 'required|boolean',
        ]);
    }
}
