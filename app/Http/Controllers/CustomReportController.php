<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Location;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomReportController extends Controller
{
    private const COLUMNS = [
        'hrw_id' => 'HRW ID', 'planned' => 'Planned', 'opened' => 'Opened',
        'closed' => 'Closed', 'status' => 'Status', 'duration' => 'Duration (min)',
        'type' => 'Work type', 'other_type' => 'Other description',
        'location' => 'Location at logging', 'area' => 'Area at logging',
        'permit' => 'Permit reference', 'notes' => 'Notes',
        'reported_by' => 'Reported by', 'opened_by' => 'Opened by', 'closed_by' => 'Closed by',
    ];

    private const DEFAULT_COLUMNS = ['hrw_id', 'opened', 'closed', 'status', 'type', 'other_type', 'location', 'area', 'permit'];

    public function index(Request $request)
    {
        $f = $this->filters($request);
        $query = $this->query($f);
        $grouped = [];
        if ($f['group_by'] !== 'none') {
            (clone $query)->chunk(500, function ($rows) use (&$grouped, $f) {
                foreach ($rows as $entry) {
                    $key = $this->groupName($entry, $f['group_by'], $f['date_basis']);
                    $grouped[$key] = ($grouped[$key] ?? 0) + 1;
                }
            });
            arsort($grouped);
        }

        return view('reports.custom', [
            'filters' => $f,
            'columns' => self::COLUMNS,
            'rows' => $query->paginate(100)->withQueryString()->through(function ($entry) use ($f) {
                $values = [];
                foreach ($f['columns'] as $column) {
                    $values[$column] = $this->value($entry, $column);
                }

                return $values;
            }),
            'total' => (clone $query)->count(),
            'grouped' => $grouped,
            'workTypes' => WorkType::orderBy('name')->get(),
            'areas' => Area::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(['id', 'name', 'code', 'status']),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $f = $this->filters($request);
        $query = $this->query($f);
        $columns = $f['columns'];

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(fn ($column) => self::COLUMNS[$column], $columns));
            $query->chunk(500, function ($rows) use ($out, $columns) {
                foreach ($rows as $entry) {
                    fputcsv($out, array_map(fn ($column) => $this->safeCell($this->value($entry, $column)), $columns));
                }
            });
            fclose($out);
        }, 'hwrt-custom-'.now()->format('Ymd-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function handover(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after:from',
        ]);
        $end = isset($data['to']) ? Carbon::parse($data['to'], config('app.timezone')) : now();
        $start = isset($data['from']) ? Carbon::parse($data['from'], config('app.timezone')) : $end->copy()->subHours(12);
        if ($end->lte($start)) {
            return back()->withErrors(['to' => 'End must be later than start.']);
        }
        $eager = ['workType', 'area', 'opener'];
        // A current-status snapshot at the selected end. Cancelled jobs never started.
        $stillOpen = Entry::with($eager)->where('opened_at', '<=', $end)
            ->where('status', '!=', 'pending')->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>', $end))
            ->orderBy('opened_at')->get();
        $activity = Entry::with($eager)->where(function ($q) use ($start, $end) {
            $q->whereBetween('opened_at', [$start, $end])->where('status', '!=', 'pending')
                ->orWhereBetween('closed_at', [$start, $end]);
        })->orderBy('opened_at')->get();
        $upcoming = Entry::with($eager)->where('status', 'pending')
            ->whereBetween('planned_start_at', [$end, $end->copy()->addHours(12)])
            ->orderBy('planned_start_at')->get();

        return view('reports.handover', compact('start', 'end', 'stillOpen', 'activity', 'upcoming'));
    }

    private function filters(Request $request): array
    {
        $f = $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'date_basis' => ['nullable', Rule::in(['opened', 'planned', 'active'])],
            'status' => ['nullable', Rule::in(['pending', 'open', 'closed', 'cancelled'])],
            'work_type_id' => 'nullable|integer|exists:work_types,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'location_text' => 'nullable|string|max:120',
            'q' => 'nullable|string|max:120',
            'group_by' => ['nullable', Rule::in(['none', 'type', 'other_type', 'location', 'area', 'status', 'day'])],
            'columns' => 'nullable|array|min:1|max:15',
            'columns.*' => ['string', Rule::in(array_keys(self::COLUMNS))],
        ]);
        $f['date_basis'] = $f['date_basis'] ?? 'opened';
        $f['group_by'] = $f['group_by'] ?? 'none';
        $f['columns'] = array_values(array_unique($f['columns'] ?? self::DEFAULT_COLUMNS));

        return $f;
    }

    private function query(array $f): Builder
    {
        $from = isset($f['from']) ? Carbon::parse($f['from'], config('app.timezone'))->startOfDay() : null;
        $to = isset($f['to']) ? Carbon::parse($f['to'], config('app.timezone'))->endOfDay() : null;
        $basis = $f['date_basis'];

        return Entry::query()->with(['workType', 'area', 'opener', 'closer'])
            ->when($basis === 'opened', fn ($q) => $q->where('status', '!=', 'pending'))
            ->when($basis === 'planned', fn ($q) => $q->whereNotNull('planned_start_at'))
            ->when($basis === 'active', fn ($q) => $q->where('status', '!=', 'pending')->where('status', '!=', 'cancelled'))
            ->when($from, function ($q) use ($from, $basis) {
                if ($basis === 'active') {
                    $q->where(fn ($w) => $w->whereNull('closed_at')->orWhere('closed_at', '>=', $from));
                } else {
                    $q->where($basis === 'planned' ? 'planned_start_at' : 'opened_at', '>=', $from);
                }
            })
            ->when($to, fn ($q) => $q->where($basis === 'planned' ? 'planned_start_at' : 'opened_at', '<=', $to))
            ->when($f['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($f['work_type_id'] ?? null, fn ($q, $value) => $q->where('work_type_id', $value))
            ->when($f['area_id'] ?? null, fn ($q, $value) => $q->where('area_id', $value))
            ->when($f['location_id'] ?? null, fn ($q, $value) => $q->where('location_id', $value))
            ->when($f['location_text'] ?? null, fn ($q, $value) => $q->where('location_label', 'like', "%{$value}%"))
            ->when($f['q'] ?? null, fn ($q, $value) => $q->where(fn ($w) => $w->where('hrw_ref', 'like', "%{$value}%")
                ->orWhere('permit_no', 'like', "%{$value}%")->orWhere('notes', 'like', "%{$value}%")
                ->orWhere('reported_by', 'like', "%{$value}%")->orWhere('other_description', 'like', "%{$value}%")))
            ->orderByDesc('opened_at')->orderByDesc('id');
    }

    private function groupName(Entry $entry, string $by, string $basis): string
    {
        return match ($by) {
            'type' => $entry->workType?->name ?? 'Unknown',
            'other_type' => $entry->workType?->is_other ? ($entry->other_description ?: 'Other, unspecified') : 'Named work type',
            'location' => $entry->location_label,
            'area' => $entry->area?->name ?? 'No mapped area',
            'status' => ucfirst($entry->status),
            'day' => ($basis === 'planned' ? $entry->planned_start_at : $entry->opened_at)->format('Y-m-d'),
            default => 'All',
        };
    }

    private function value(Entry $entry, string $column): string|int|float|null
    {
        return match ($column) {
            'hrw_id' => $entry->hrw_ref,
            'planned' => $entry->planned_start_at?->format('Y-m-d H:i'),
            'opened' => $entry->status === 'pending' ? null : $entry->opened_at->format('Y-m-d H:i'),
            'closed' => $entry->closed_at?->format('Y-m-d H:i'),
            'status' => ucfirst($entry->status),
            'duration' => $entry->closed_at && $entry->status !== 'cancelled' ? round($entry->elapsedSeconds() / 60, 1) : null,
            'type' => $entry->workType?->name,
            'other_type' => $entry->other_description,
            'location' => $entry->location_label,
            'area' => $entry->area?->name,
            'permit' => $entry->permit_no,
            'notes' => $entry->notes,
            'reported_by' => $entry->reported_by,
            'opened_by' => $entry->opener?->name,
            'closed_by' => $entry->closer?->name,
            default => null,
        };
    }

    private function safeCell(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
