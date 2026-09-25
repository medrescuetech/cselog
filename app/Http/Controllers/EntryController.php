<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Location;
use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntryController extends Controller
{
    public function create()
    {
        return view('entries.create', [
            'workTypes' => WorkType::active()->get(),
            'defaultType' => WorkType::active()->where('is_default', true)->first() ?? WorkType::active()->first(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'work_type_id' => 'required|exists:work_types,id',
            'location_id' => 'nullable|exists:locations,id',
            'location_label' => 'required_without:location_id|nullable|string|max:160',
            'easting' => 'required_without:location_id|nullable|numeric',
            'northing' => 'required_without:location_id|nullable|numeric',
            'other_description' => 'nullable|string|max:160',
            'notes' => 'nullable|string|max:2000',
            'permit_no' => 'nullable|string|max:60',
            'reported_by' => 'nullable|string|max:120',
            'opened_at' => 'nullable|date',
            'late_reason' => 'nullable|string|max:255',
        ]);

        $type = WorkType::findOrFail($d['work_type_id']);
        if ($type->is_other && blank($d['other_description'] ?? null)) {
            return back()->withErrors(['other_description' => 'Describe the high risk work when Other is selected.'])->withInput();
        }
        if ($type->requires_note && blank($d['notes'] ?? null)) {
            return back()->withErrors(['notes' => "{$type->name} requires notes."])->withInput();
        }

        $location = isset($d['location_id']) ? Location::findOrFail($d['location_id']) : null;
        $e = $location?->easting ?? (float) $d['easting'];
        $n = $location?->northing ?? (float) $d['northing'];

        $openedAt = now();
        $changes = null;
        if (! empty($d['opened_at'])) {
            $openedAt = Carbon::parse($d['opened_at'], config('app.timezone'));
            if ($openedAt->isFuture()) {
                return back()->withErrors(['opened_at' => 'Time cannot be in the future.'])->withInput();
            }
            $changes = ['logged_late' => true, 'reason' => $d['late_reason'] ?? null, 'recorded_at' => now()->toIso8601String()];
        }

        $entry = Entry::create([
            'location_id' => $location?->id,
            'location_label' => $location?->name ?? $d['location_label'],
            'easting' => $e,
            'northing' => $n,
            'area_id' => $location?->area_id ?? Area::containing($e, $n)?->id,
            'work_type_id' => $type->id,
            'other_description' => $type->is_other ? ($d['other_description'] ?? null) : null,
            'notes' => $d['notes'] ?? null,
            'permit_no' => $d['permit_no'] ?? null,
            'reported_by' => $d['reported_by'] ?? null,
            'status' => 'open',
            'opened_at' => $openedAt,
            'opened_by' => $request->user()->id,
        ]);
        $entry->log('created', $changes);

        if ($location) {
            $location->increment('usage_count');
            $location->forceFill(['last_used_at' => now()])->save();
        }

        if ($request->expectsJson()) {
            return response()->json($entry, 201);
        }

        return redirect()->route('board')->with('highlight', $entry->id);
    }

    public function close(Request $request, Entry $entry)
    {
        abort_unless($entry->status === 'open', 409, 'Entry is not open.');
        $d = $request->validate(['close_note' => 'nullable|string|max:2000']);
        $entry->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
            'close_note' => $d['close_note'] ?? null,
        ]);
        $entry->log('closed', ['close_note' => $d['close_note'] ?? null]);

        if ($request->expectsJson()) {
            return response()->json($entry);
        }

        return back()->with('status', "Closed {$entry->location_label}.");
    }

    public function board()
    {
        return view('entries.board', [
            'entries' => $this->openEntries()->map(fn ($e) => $this->serialise($e))->values(),
        ]);
    }

    public function openJson()
    {
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'entries' => $this->openEntries()->map(fn ($e) => $this->serialise($e)),
        ]);
    }

    public function history(Request $request)
    {
        $f = $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date',
            'status' => 'nullable|in:open,closed,cancelled',
            'work_type_id' => 'nullable|integer', 'area_id' => 'nullable|integer',
            'q' => 'nullable|string|max:120',
        ]);
        $rows = $this->historyQuery($f)->with('events.actor')->paginate(50)->withQueryString();

        return view('entries.history', [
            'entries' => $rows, 'filters' => $f,
            'workTypes' => WorkType::orderBy('name')->get(),
            'areas' => Area::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $f = $request->only(['from', 'to', 'status', 'work_type_id', 'area_id', 'q']);
        $q = $this->historyQuery($f);
        $name = 'csem-history-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['hrw_id', 'id', 'opened_at', 'closed_at', 'duration_min', 'status', 'location', 'easting', 'northing',
                'area', 'work_type', 'other_type', 'permit_no', 'reported_by', 'notes', 'opened_by', 'closed_by', 'close_note']);
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    fputcsv($out, [
                        $e->hrw_ref, $e->id, $e->opened_at, $e->closed_at,
                        $e->closed_at ? round($e->elapsedSeconds() / 60) : null,
                        $e->status, $e->location_label, $e->easting, $e->northing,
                        $e->area?->name, $e->workType?->name, $e->other_description, $e->permit_no, $e->reported_by, $e->notes,
                        $e->opener?->name, $e->closer?->name, $e->close_note,
                    ]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    private function openEntries()
    {
        return Entry::open()->with(['workType', 'area', 'opener'])->get();
    }

    private function historyQuery(array $f)
    {
        return Entry::query()->with(['workType', 'area', 'opener', 'closer'])
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('opened_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('opened_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['work_type_id'] ?? null, fn ($q, $v) => $q->where('work_type_id', $v))
            ->when($f['area_id'] ?? null, fn ($q, $v) => $q->where('area_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('hrw_ref', 'like', "%{$v}%")
                ->orWhere('location_label', 'like', "%{$v}%")
                ->orWhere('notes', 'like', "%{$v}%")
                ->orWhere('other_description', 'like', "%{$v}%")
                ->orWhere('permit_no', 'like', "%{$v}%")
                ->orWhere('reported_by', 'like', "%{$v}%")))
            ->orderByDesc('opened_at');
    }

    private function serialise(Entry $e): array
    {
        return [
            'id' => $e->id,
            'hrw_ref' => $e->hrw_ref,
            'location' => $e->location_label,
            'location_id' => $e->location_id,
            'easting' => $e->easting, 'northing' => $e->northing,
            'area' => $e->area?->name,
            'type' => $e->workType->name,
            'type_display' => $e->workType->is_other && $e->other_description
                ? 'Other — '.$e->other_description
                : $e->workType->name,
            'other_description' => $e->other_description,
            'colour' => $e->workType->colour,
            'notes' => $e->notes, 'permit_no' => $e->permit_no, 'reported_by' => $e->reported_by,
            'opened_at' => $e->opened_at->toIso8601String(),
            'opened_by' => $e->opener?->name,
            'elapsed_s' => $e->elapsedSeconds(),
            'band' => $e->ageBand(),
        ];
    }
}
