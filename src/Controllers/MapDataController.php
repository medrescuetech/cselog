<?php

declare(strict_types=1);

namespace CseLog\Controllers;

use CseLog\Http;
use CseLog\Repo;

final class MapDataController
{
    /** Map metadata plus overlays, consumed by every map screen. */
    public function show(array $params): void
    {
        $map = Repo::map(isset($params['id']) ? (int) $params['id'] : null);
        $mapId = (int) $map['id'];

        Http::json([
            'id' => $mapId,
            'name' => $map['name'],
            'image' => $map['image_path'],
            'width' => (int) $map['width_px'],
            'height' => (int) $map['height_px'],
            'crs' => $map['crs'],
            'georeference' => $map['georeference'] === null ? null : json_decode((string) $map['georeference'], true),
            'areas' => array_map(static fn (array $a): array => [
                'id' => (int) $a['id'],
                'name' => $a['name'],
                'kind' => $a['kind'],
                'colour' => $a['colour'],
                'rings' => json_decode((string) $a['geometry_px'], true),
            ], Repo::areas($mapId)),
            'landmarks' => array_map(static fn (array $l): array => [
                'id' => (int) $l['id'],
                'name' => $l['name'],
                'category' => $l['category'],
                'colour' => $l['colour'],
                'x' => (float) $l['x'],
                'y' => (float) $l['y'],
                'notes' => $l['notes'],
            ], Repo::landmarks($mapId)),
        ]);
    }
}
