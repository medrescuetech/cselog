<?php

declare(strict_types=1);

namespace CseLog;

/** Shared queries used across controllers. */
final class Repo
{
    /** @return array<string, mixed> */
    public static function site(): array
    {
        $site = Db::first('SELECT * FROM sites WHERE active = 1 ORDER BY id LIMIT 1');
        if ($site === null) {
            throw new \RuntimeException('No site configured. Run: php bin/install.php');
        }

        return $site;
    }

    /** @return array<string, mixed> */
    public static function map(?int $id = null): array
    {
        $map = $id !== null
            ? Db::first('SELECT * FROM maps WHERE id = ?', [$id])
            : Db::first('SELECT * FROM maps WHERE active = 1 ORDER BY is_default DESC, id LIMIT 1');

        if ($map === null) {
            throw new \RuntimeException('No map configured. Run: php bin/install.php');
        }

        return $map;
    }

    /** @return array<int, array<string, mixed>> */
    public static function maps(): array
    {
        return Db::all('SELECT * FROM maps WHERE active = 1 ORDER BY is_default DESC, name');
    }

    /** @return array<int, array<string, mixed>> */
    public static function workTypes(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM work_types' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, name';

        return Db::all($sql);
    }

    /** @return array<int, array<string, mixed>> */
    public static function areas(int $mapId): array
    {
        return Db::all('SELECT * FROM areas WHERE map_id = ? AND active = 1 ORDER BY name', [$mapId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function landmarks(int $mapId): array
    {
        return Db::all('SELECT * FROM landmarks WHERE map_id = ? AND active = 1 ORDER BY name', [$mapId]);
    }

    /** The area whose polygon contains the given pixel point, if any. */
    public static function areaAt(int $mapId, ?float $x, ?float $y): ?int
    {
        if ($x === null || $y === null) {
            return null;
        }

        foreach (self::areas($mapId) as $area) {
            $rings = json_decode((string) $area['geometry_px'], true);
            if (is_array($rings) && Support::pointInPolygon($x, $y, $rings)) {
                return (int) $area['id'];
            }
        }

        return null;
    }

    /**
     * Open entries with everything the board and map need.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function openEntries(int $siteId): array
    {
        $rows = Db::all(
            'SELECT e.*, w.name AS work_type, w.colour AS work_colour, a.name AS area_name,
                    u.display_name AS opened_by_name, l.verified AS location_verified
               FROM entries e
               JOIN work_types w ON w.id = e.work_type_id
               LEFT JOIN areas a ON a.id = e.area_id
               LEFT JOIN users u ON u.id = e.opened_by
               LEFT JOIN locations l ON l.id = e.location_id
              WHERE e.site_id = ? AND e.status = ?
              ORDER BY e.opened_at ASC',
            [$siteId, 'open']
        );

        foreach ($rows as &$row) {
            $row['age_seconds'] = Support::durationSeconds((string) $row['opened_at']);
            $row['age_human'] = Support::humanDuration((int) $row['age_seconds']);
            $row['age_level'] = Support::ageLevel((int) $row['age_seconds']);
            $row['ad_hoc'] = $row['location_id'] === null;
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public static function locationSuggestions(int $siteId, string $query = '', int $limit = 12): array
    {
        $params = [$siteId];
        $where = "l.site_id = ? AND l.status = 'active' AND l.merged_into_id IS NULL";

        if ($query !== '') {
            $like = '%' . strtolower($query) . '%';
            $where .= ' AND (LOWER(l.name) LIKE ? OR LOWER(COALESCE(l.code, \'\')) LIKE ?
                        OR EXISTS (SELECT 1 FROM location_aliases al WHERE al.location_id = l.id AND LOWER(al.alias) LIKE ?))';
            array_push($params, $like, $like, $like);
        }

        $params[] = $limit;

        return Db::all(
            "SELECT l.*, a.name AS area_name
               FROM locations l
               LEFT JOIN areas a ON a.id = l.area_id
              WHERE $where
              ORDER BY l.last_used_at DESC, l.usage_count DESC, l.name
              LIMIT ?",
            $params
        );
    }

    public static function logEvent(int $entryId, string $event, array $changes = []): void
    {
        Db::insert('entry_events', [
            'entry_id' => $entryId,
            'event' => $event,
            'actor_id' => Auth::id(),
            'actor_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'changes' => $changes === [] ? null : json_encode($changes),
            'occurred_at' => Support::nowUtc(),
        ]);
    }

    public static function touchLocation(int $locationId): void
    {
        Db::run(
            'UPDATE locations SET usage_count = usage_count + 1, last_used_at = ? WHERE id = ?',
            [Support::nowUtc(), $locationId]
        );
    }
}
