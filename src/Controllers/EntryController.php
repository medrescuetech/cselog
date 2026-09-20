<?php

declare(strict_types=1);

namespace CseLog\Controllers;

use CseLog\Auth;
use CseLog\Db;
use CseLog\Http;
use CseLog\Repo;
use CseLog\Support;

final class EntryController
{
    /** The quick log form. */
    public function create(): void
    {
        $site = Repo::site();
        $map = Repo::map(Http::intInput('map'));

        Http::render('log', [
            'title' => 'Log entry',
            'site' => $site,
            'map' => $map,
            'maps' => Repo::maps(),
            'workTypes' => Repo::workTypes(),
            'recent' => Repo::locationSuggestions((int) $site['id'], '', 8),
        ]);
    }

    /** Stores the entry. A new location sends the user on to the pin screen. */
    public function store(): void
    {
        $site = Repo::site();
        $map = Repo::map(Http::intInput('map_id'));
        $workTypeId = Http::intInput('work_type_id');
        $workType = $workTypeId === null
            ? null
            : Db::first('SELECT * FROM work_types WHERE id = ? AND active = 1', [$workTypeId]);
        $notes = Http::input('notes', '');
        $newLocation = Http::input('location_mode') === 'new';
        $locationId = $newLocation ? null : Http::intInput('location_id');

        if ($workType === null) {
            Http::flash('Choose a work type — that one is no longer available.', 'error');
            Http::redirect('/log');
        }

        if ((int) $workType['requires_note'] === 1 && ($notes === null || $notes === '')) {
            Http::flash($workType['name'] . ' needs a note describing the work.', 'error');
            Http::redirect('/log');
        }

        if (!$newLocation && $locationId === null) {
            Http::flash('Pick a location, or choose "New location" to drop a pin.', 'error');
            Http::redirect('/log');
        }

        $location = $locationId === null ? null : Db::first(
            "SELECT * FROM locations
              WHERE id = ? AND site_id = ? AND status = 'active' AND merged_into_id IS NULL",
            [$locationId, (int) $site['id']]
        );
        if ($locationId !== null && $location === null) {
            Http::flash('That location has been archived or merged — pick another.', 'error');
            Http::redirect('/log');
        }

        $openedAtInput = Http::input('opened_at');
        $openedAt = $openedAtInput === null || $openedAtInput === ''
            ? Support::nowUtc()
            : Support::toUtc($openedAtInput);

        $entryId = Db::transaction(static function () use ($site, $map, $location, $workType, $notes, $openedAt): int {
            $entryId = Db::insert('entries', [
                'site_id' => (int) $site['id'],
                // A catalogue location keeps the map its pin was placed on; only a
                // brand-new pin belongs to the map chosen on the form.
                'map_id' => (int) ($location['map_id'] ?? $map['id']),
                'location_id' => $location['id'] ?? null,
                'location_label' => $location['name'] ?? 'Pin pending',
                'x' => $location['x'] ?? null,
                'y' => $location['y'] ?? null,
                'area_id' => $location['area_id'] ?? null,
                'work_type_id' => (int) $workType['id'],
                'notes' => $notes,
                'reported_by' => Http::input('reported_by'),
                'status' => 'open',
                'opened_at' => $openedAt,
                'opened_by' => (int) Auth::id(),
                'created_at' => Support::nowUtc(),
            ]);

            Repo::logEvent($entryId, 'created', [
                'location' => $location['name'] ?? 'pin pending',
                'work_type' => $workType['name'],
            ]);

            if ($location !== null) {
                Repo::touchLocation((int) $location['id']);
            }

            return $entryId;
        });

        if ($location === null) {
            Http::redirect('/entries/' . $entryId . '/pin');
        }

        Http::flash('Logged: ' . $location['name'] . '.');
        Http::redirect('/board?highlight=' . $entryId);
    }

    /** Pin-drop screen shown after logging against a new location. */
    public function pinForm(array $params): void
    {
        $entry = $this->findPendingPinEntry((int) $params['id']);
        $map = Repo::map((int) $entry['map_id']);

        Http::render('pin', [
            'title' => 'Place the pin',
            'entry' => $entry,
            'map' => $map,
            'areas' => Repo::areas((int) $map['id']),
            'landmarks' => Repo::landmarks((int) $map['id']),
        ]);
    }

    /** Saves the dropped pin, optionally promoting it to the location catalogue. */
    public function pinStore(array $params): void
    {
        $entry = $this->findPendingPinEntry((int) $params['id']);
        $map = Repo::map((int) $entry['map_id']);
        $x = Http::floatInput('x');
        $y = Http::floatInput('y');

        if ($x === null || $y === null) {
            Http::flash('Drop a pin on the map first.', 'error');
            Http::redirect('/entries/' . $entry['id'] . '/pin');
        }

        $save = Http::input('save_location') === '1';
        $name = trim((string) Http::input('location_name', ''));

        if ($save && $name === '') {
            Http::flash('Give the location a name, or choose "Just this once".', 'error');
            Http::redirect('/entries/' . $entry['id'] . '/pin');
        }

        $areaId = Repo::areaAt((int) $map['id'], $x, $y);

        Db::transaction(static function () use ($entry, $map, $x, $y, $areaId, $save, $name): void {
            $locationId = null;

            if ($save) {
                $existing = Db::first(
                    'SELECT id FROM locations WHERE site_id = ? AND LOWER(name) = ?',
                    [(int) $entry['site_id'], strtolower($name)]
                );

                if ($existing !== null) {
                    $locationId = (int) $existing['id'];
                } else {
                    $locationId = Db::insert('locations', [
                        'site_id' => (int) $entry['site_id'],
                        'map_id' => (int) $map['id'],
                        'name' => $name,
                        'area_id' => $areaId,
                        'x' => $x,
                        'y' => $y,
                        'status' => 'active',
                        'verified' => 0,
                        'usage_count' => 0,
                        'created_by' => Auth::id(),
                        'created_at' => Support::nowUtc(),
                    ]);
                }

                Repo::touchLocation($locationId);
            }

            Db::update('entries', [
                'location_id' => $locationId,
                'location_label' => $save ? $name : (Http::input('adhoc_label') ?: 'Dropped pin'),
                'x' => $x,
                'y' => $y,
                'area_id' => $areaId,
            ], ['id' => (int) $entry['id']]);

            Repo::logEvent((int) $entry['id'], 'updated', [
                'pin' => ['x' => $x, 'y' => $y],
                'location_saved' => $save ? $name : false,
            ]);
        });

        Http::flash($save
            ? 'Pin placed and "' . $name . '" saved to the location list.'
            : 'Pin placed for this entry only.');
        Http::redirect('/board?highlight=' . $entry['id']);
    }

    public function close(array $params): void
    {
        $entry = $this->findEntry((int) $params['id']);

        if ($entry['status'] !== 'open') {
            Http::flash('That entry is already ' . $entry['status'] . '.', 'error');
            Http::redirect('/board');
        }

        Db::update('entries', [
            'status' => 'closed',
            'closed_at' => Support::nowUtc(),
            'closed_by' => Auth::id(),
            'close_note' => Http::input('close_note'),
        ], ['id' => (int) $entry['id']]);

        Repo::logEvent((int) $entry['id'], 'closed', ['note' => Http::input('close_note')]);

        if (Http::wantsJson()) {
            Http::json(['ok' => true]);
        }

        Http::flash('Closed: ' . $entry['location_label'] . '.');
        Http::redirect('/board');
    }

    public function cancel(array $params): void
    {
        $entry = $this->findEntry((int) $params['id']);

        Db::update('entries', [
            'status' => 'cancelled',
            'closed_at' => Support::nowUtc(),
            'closed_by' => Auth::id(),
            'close_note' => Http::input('close_note', 'Logged in error'),
        ], ['id' => (int) $entry['id']]);

        Repo::logEvent((int) $entry['id'], 'cancelled', ['note' => Http::input('close_note')]);
        Http::flash('Entry cancelled.');
        Http::redirect('/board');
    }

    public function reopen(array $params): void
    {
        $entry = $this->findEntry((int) $params['id']);

        Db::update('entries', [
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ], ['id' => (int) $entry['id']]);

        Repo::logEvent((int) $entry['id'], 'reopened');
        Http::flash('Entry reopened.');
        Http::redirect('/board');
    }

    public function show(array $params): void
    {
        $entry = Db::first(
            'SELECT e.*, w.name AS work_type, w.colour AS work_colour, a.name AS area_name,
                    o.display_name AS opened_by_name, c.display_name AS closed_by_name
               FROM entries e
               JOIN work_types w ON w.id = e.work_type_id
               LEFT JOIN areas a ON a.id = e.area_id
               LEFT JOIN users o ON o.id = e.opened_by
               LEFT JOIN users c ON c.id = e.closed_by
              WHERE e.id = ?',
            [(int) $params['id']]
        );

        if ($entry === null) {
            Http::notFound('Entry not found.');
        }

        Http::render('entry', [
            'title' => 'Entry #' . $entry['id'],
            'entry' => $entry,
            'events' => Db::all(
                'SELECT ev.*, u.display_name AS actor_name
                   FROM entry_events ev
                   LEFT JOIN users u ON u.id = ev.actor_id
                  WHERE ev.entry_id = ?
                  ORDER BY ev.occurred_at, ev.id',
                [(int) $entry['id']]
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function findEntry(int $id): array
    {
        $entry = Db::first('SELECT * FROM entries WHERE id = ?', [$id]);
        if ($entry === null) {
            Http::notFound('Entry not found.');
        }

        return $entry;
    }

    /**
     * An open entry still awaiting its first pin. Anything already pinned is a
     * historical record and is moved through the location catalogue instead.
     *
     * @return array<string, mixed>
     */
    private function findPendingPinEntry(int $id): array
    {
        $entry = $this->findEntry($id);

        if ($entry['status'] !== 'open' || $entry['location_id'] !== null || $entry['x'] !== null) {
            Http::flash('That entry already has a location — edit it from the entry page.', 'error');
            Http::redirect('/entries/' . $id);
        }

        return $entry;
    }
}
