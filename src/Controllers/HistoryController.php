<?php

declare(strict_types=1);

namespace CseLog\Controllers;

use CseLog\Db;
use CseLog\Http;
use CseLog\Repo;
use CseLog\Support;

final class HistoryController
{
    public function index(): void
    {
        $site = Repo::site();
        [$sql, $params] = $this->buildQuery((int) $site['id']);
        $rows = Db::all($sql . ' ORDER BY e.opened_at DESC LIMIT 500', $params);
        $areas = Repo::siteAreas((int) $site['id']);

        Http::render('history', [
            'title' => 'History',
            'site' => $site,
            'rows' => $this->decorate($rows),
            'workTypes' => Repo::workTypes(false),
            'areas' => $areas,
            'mapNames' => array_unique(array_column($areas, 'map_name')),
            'filters' => $this->filters(),
            'summary' => $this->summary($rows),
        ]);
    }

    public function exportCsv(): void
    {
        $site = Repo::site();
        [$sql, $params] = $this->buildQuery((int) $site['id']);
        $rows = $this->decorate(Db::all($sql . ' ORDER BY e.opened_at DESC', $params));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cse-log-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'wb');
        fputcsv($out, [
            'id', 'status', 'location', 'area', 'work_type', 'opened_at_local', 'closed_at_local',
            'duration_hhmm', 'notes', 'reported_by', 'logged_by', 'closed_by', 'close_note', 'saved_location',
        ]);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'],
                $row['status'],
                $row['location_label'],
                $row['area_name'],
                $row['work_type'],
                Support::local((string) $row['opened_at'], 'Y-m-d H:i'),
                $row['closed_at'] === null ? '' : Support::local((string) $row['closed_at'], 'Y-m-d H:i'),
                $row['duration_human'],
                $row['notes'],
                $row['reported_by'],
                $row['opened_by_name'],
                $row['closed_by_name'],
                $row['close_note'],
                $row['location_id'] === null ? 'ad-hoc pin' : 'catalogue',
            ]);
        }

        fclose($out);
        exit;
    }

    /** Shift handover: everything still open, plus what happened today. */
    public function shiftReport(): void
    {
        $site = Repo::site();
        $since = gmdate('Y-m-d H:i:s', time() - 12 * 3600);

        Http::render('shift-report', [
            'title' => 'Shift report',
            'site' => $site,
            'open' => Repo::openEntries((int) $site['id']),
            'closed' => $this->decorate(Db::all(
                "SELECT e.*, w.name AS work_type, a.name AS area_name,
                        o.display_name AS opened_by_name, c.display_name AS closed_by_name
                   FROM entries e
                   JOIN work_types w ON w.id = e.work_type_id
                   LEFT JOIN areas a ON a.id = e.area_id
                   LEFT JOIN users o ON o.id = e.opened_by
                   LEFT JOIN users c ON c.id = e.closed_by
                  WHERE e.site_id = ? AND e.status = 'closed' AND e.closed_at >= ?
                  ORDER BY e.closed_at DESC",
                [(int) $site['id'], $since]
            )),
            'generatedAt' => Support::local(Support::nowUtc(), 'D j M Y, H:i'),
        ]);
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function buildQuery(int $siteId): array
    {
        $filters = $this->filters();
        $where = ['e.site_id = ?'];
        $params = [$siteId];

        if ($filters['from'] !== '') {
            $where[] = 'e.opened_at >= ?';
            $params[] = Support::toUtc($filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $where[] = 'e.opened_at <= ?';
            $params[] = Support::toUtc($filters['to'] . ' 23:59:59');
        }
        if ($filters['status'] !== '') {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['work_type_id'] !== '') {
            $where[] = 'e.work_type_id = ?';
            $params[] = (int) $filters['work_type_id'];
        }
        if ($filters['area_id'] !== '') {
            $where[] = 'e.area_id = ?';
            $params[] = (int) $filters['area_id'];
        }
        if ($filters['q'] !== '') {
            $where[] = '(LOWER(e.location_label) LIKE ? OR LOWER(COALESCE(e.notes, \'\')) LIKE ?)';
            $like = '%' . strtolower($filters['q']) . '%';
            array_push($params, $like, $like);
        }

        $sql = 'SELECT e.*, w.name AS work_type, w.colour AS work_colour, a.name AS area_name,
                       o.display_name AS opened_by_name, c.display_name AS closed_by_name
                  FROM entries e
                  JOIN work_types w ON w.id = e.work_type_id
                  LEFT JOIN areas a ON a.id = e.area_id
                  LEFT JOIN users o ON o.id = e.opened_by
                  LEFT JOIN users c ON c.id = e.closed_by
                 WHERE ' . implode(' AND ', $where);

        return [$sql, $params];
    }

    /** @return array<string, string> */
    private function filters(): array
    {
        return [
            'from' => (string) Http::input('from', ''),
            'to' => (string) Http::input('to', ''),
            'status' => (string) Http::input('status', ''),
            'work_type_id' => (string) Http::input('work_type_id', ''),
            'area_id' => (string) Http::input('area_id', ''),
            'q' => (string) Http::input('q', ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function decorate(array $rows): array
    {
        foreach ($rows as &$row) {
            $seconds = Support::durationSeconds((string) $row['opened_at'], $row['closed_at'] === null ? null : (string) $row['closed_at']);
            $row['duration_seconds'] = $seconds;
            $row['duration_human'] = Support::humanDuration($seconds);
            $row['age_level'] = $row['status'] === 'open' ? Support::ageLevel($seconds) : 'grey';
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function summary(array $rows): array
    {
        $durations = [];
        foreach ($rows as $row) {
            if ($row['status'] === 'closed' && $row['closed_at'] !== null) {
                $durations[] = Support::durationSeconds((string) $row['opened_at'], (string) $row['closed_at']);
            }
        }

        return [
            'count' => count($rows),
            'open' => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'open')),
            'median_duration' => $durations === [] ? '—' : Support::humanDuration($this->median($durations)),
            'longest' => $durations === [] ? '—' : Support::humanDuration(max($durations)),
        ];
    }

    /** @param array<int, int> $values */
    private function median(array $values): int
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1 ? $values[$middle] : (int) (($values[$middle - 1] + $values[$middle]) / 2);
    }
}
