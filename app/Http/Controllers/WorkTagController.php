<?php

namespace App\Http\Controllers;

use App\Models\WorkTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkTagController extends Controller
{
    public function index()
    {
        return view('settings.work-tags', ['tags' => WorkTag::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80|unique:work_tags,name']);
        WorkTag::create(['name' => trim($data['name']), 'active' => true]);

        return back()->with('status', 'Activity tag added.');
    }

    public function update(Request $request, WorkTag $workTag)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('work_tags', 'name')->ignore($workTag->id)],
            'active' => 'required|boolean',
        ]);
        $workTag->update(['name' => trim($data['name']), 'active' => (bool) $data['active']]);

        return back()->with('status', 'Activity tag updated. Existing job tags remain in reports.');
    }
}
