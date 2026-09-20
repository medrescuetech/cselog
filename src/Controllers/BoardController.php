<?php

declare(strict_types=1);

namespace CseLog\Controllers;

use CseLog\Config;
use CseLog\Http;
use CseLog\Repo;
use CseLog\Support;

final class BoardController
{
    public function index(): void
    {
        $site = Repo::site();

        Http::render('board', [
            'title' => 'Open board',
            'site' => $site,
            'entries' => Repo::openEntries((int) $site['id']),
            'highlight' => Http::intInput('highlight'),
            'autoRefresh' => true,
        ]);
    }

    public function map(): void
    {
        $site = Repo::site();
        $map = Repo::map(Http::intInput('map'));

        Http::render('map', [
            'title' => 'Live map',
            'site' => $site,
            'map' => $map,
            'maps' => Repo::maps(),
            'wallboard' => Http::input('wallboard') === '1',
            'autoRefresh' => false,
        ]);
    }

    /** JSON feed polled by the map and the board. */
    public function openEntriesJson(): void
    {
        $site = Repo::site();
        $entries = Repo::openEntries((int) $site['id']);

        $payload = array_map(static fn (array $e): array => [
            'id' => (int) $e['id'],
            'label' => $e['location_label'],
            'work_type' => $e['work_type'],
            'colour' => $e['work_colour'],
            'area' => $e['area_name'],
            'notes' => $e['notes'],
            'reported_by' => $e['reported_by'],
            'opened_at' => Support::local((string) $e['opened_at']),
            'opened_by' => $e['opened_by_name'],
            'age_seconds' => (int) $e['age_seconds'],
            'age_human' => $e['age_human'],
            'age_level' => $e['age_level'],
            'ad_hoc' => (bool) $e['ad_hoc'],
            'map_id' => (int) $e['map_id'],
            'x' => $e['x'] === null ? null : (float) $e['x'],
            'y' => $e['y'] === null ? null : (float) $e['y'],
        ], $entries);

        Http::json([
            'server_time' => Support::local(Support::nowUtc(), 'H:i:s'),
            'thresholds' => [
                'amber_hours' => Config::int('ALERT_AMBER_HOURS', 2),
                'red_hours' => Config::int('ALERT_RED_HOURS', 4),
            ],
            'entries' => $payload,
        ]);
    }
}
