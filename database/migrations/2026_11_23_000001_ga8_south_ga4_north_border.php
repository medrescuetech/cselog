<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site mark-up: the shared GA1 bottom / GA8 South & GA4 top border moves north to
 * N 7,718,867. Only the edge vertices are adjusted; area colours, notes and any
 * admin edits are preserved. Matches sitemap/features/ga-areas.geojson.
 */
return new class extends Migration
{
    private const TARGET = 7718867;

    private const ROLLBACK = 7718800;

    /** name => which horizontal edge carries the shared border. */
    private const AREAS = ['GA1' => 'min', 'GA4' => 'max', 'GA8 South' => 'max'];

    public function up(): void
    {
        if (! Schema::hasTable('areas')) {
            return;
        }
        foreach (self::AREAS as $name => $edge) {
            $this->moveEdge($name, $edge, self::TARGET);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('areas')) {
            return;
        }
        foreach (self::AREAS as $name => $edge) {
            $this->moveEdge($name, $edge, self::ROLLBACK);
        }
    }

    private function moveEdge(string $name, string $edge, float $target): void
    {
        $row = DB::table('areas')->where('kind', 'ga')->where('name', $name)->first();
        if (! $row) {
            return;
        }
        $geom = is_string($row->geometry) ? json_decode($row->geometry, true) : $row->geometry;
        if (! is_array($geom) || ! is_array($geom['coordinates'] ?? null)) {
            return;
        }

        $ys = [];
        $collect = function ($coords) use (&$collect, &$ys) {
            if (is_array($coords) && is_numeric($coords[0] ?? null) && is_numeric($coords[1] ?? null)) {
                $ys[] = $coords[1];

                return;
            }
            foreach ((array) $coords as $c) {
                $collect($c);
            }
        };
        $collect($geom['coordinates']);
        if ($ys === []) {
            return;
        }
        $edgeY = $edge === 'min' ? min($ys) : max($ys);
        if ($edgeY === $target) {
            return;
        }

        $set = function (&$coords) use (&$set, $edgeY, $target) {
            if (is_array($coords) && is_numeric($coords[0] ?? null) && is_numeric($coords[1] ?? null)) {
                if ($coords[1] == $edgeY) {
                    $coords[1] = $target;
                }

                return;
            }
            foreach ($coords as &$c) {
                $set($c);
            }
        };
        $set($geom['coordinates']);

        DB::table('areas')->where('id', $row->id)->update(['geometry' => json_encode($geom)]);
    }
};
