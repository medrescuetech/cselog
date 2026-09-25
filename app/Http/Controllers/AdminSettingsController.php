<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', [
            'users' => User::orderBy('name')->get(),
            'workTypes' => WorkType::orderBy('sort_order')->orderBy('name')->get(),
            'roles' => array_keys(User::ROLES),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);

        $user->update(['role' => $validated['role']]);

        return back()->with('status', "Role updated to {$user->role} for {$user->name}.");
    }

    public function storeWorkType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:work_types,name',
            'colour' => 'required|string|max:20',
            'requires_note' => 'nullable|boolean',
        ]);

        $maxSort = WorkType::max('sort_order') ?? 0;

        WorkType::create([
            'name' => $validated['name'],
            'colour' => $validated['colour'],
            'requires_note' => $request->boolean('requires_note'),
            'sort_order' => $maxSort + 1,
            'active' => true,
        ]);

        return back()->with('status', 'Work type added successfully.');
    }

    public function toggleWorkType(WorkType $workType)
    {
        $workType->update(['active' => ! $workType->active]);

        $status = $workType->active ? 'activated' : 'deactivated';

        return back()->with('status', "Work type {$workType->name} {$status}.");
    }
}
