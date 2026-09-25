<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Entry;
use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function logbook(Request $request)
    {
        $days = (int) $request->query('days', 7);
        if (! in_array($days, [3, 7, 14, 30], true)) {
            $days = 7;
        }

        $entries = Entry::query()
            ->with(['workType', 'area', 'opener', 'closer'])
            ->where('opened_at', '>=', now()->subDays($days)->startOfDay())
            ->orderByDesc('opened_at')
            ->paginate(100)
            ->withQueryString();

        return view('reports.logbook', compact('entries', 'days'));
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->query($filters);

        $summary = [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->where('status', 'open')->count(),
            'closed' => (clone $query)->where('status', 'closed')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
        ];

        return view('reports.index', [
            'entries' => $query->paginate(100)->withQueryString(),
            'summary' => $summary,
            'filters' => $filters,
            'workTypes' => WorkType::query()->orderBy('name')->get(),
            'areas' => Area::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->query($filters);
        $name = 'hwrt-report-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'hrw_id', 'opened_at', 'closed_at', 'status', 'duration_min',
                'type', 'other_type', 'location', 'area', 'permit_no',
                'reported_by', 'notes', 'opened_by', 'closed_by', 'close_note',
            ]);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    fputcsv($out, [
                        $e->hrw_ref,
                        $e->opened_at,
                        $e->closed_at,
                        $e->status,
                        $e->closed_at ? round($e->elapsedSeconds() / 60) : null,
                        $e->workType?->name,
                        $e->other_description,
                        $e->location_label,
                        $e->area?->name,
                        $e->permit_no,
                        $e->reported_by,
                        $e->notes,
                        $e->opener?->name,
                        $e->closer?->name,
                        $e->close_note,
                    ]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => 'nullable|in:open,closed,cancelled',
            'work_type_id' => 'nullable|integer|exists:work_types,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'q' => 'nullable|string|max:120',
        ]);
    }

    private function query(array $f)
    {
        return Entry::query()
            ->with(['workType', 'area', 'opener', 'closer'])
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('opened_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('opened_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['work_type_id'] ?? null, fn ($q, $v) => $q->where('work_type_id', $v))
            ->when($f['area_id'] ?? null, fn ($q, $v) => $q->where('area_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('hrw_ref', 'like', "%{$v}%")
                    ->orWhere('location_label', 'like', "%{$v}%")
                    ->orWhere('notes', 'like', "%{$v}%")
                    ->orWhere('permit_no', 'like', "%{$v}%")
                    ->orWhere('reported_by', 'like', "%{$v}%")
                    ->orWhere('other_description', 'like', "%{$v}%");
            }))
            ->orderByDesc('opened_at');
    }
}
