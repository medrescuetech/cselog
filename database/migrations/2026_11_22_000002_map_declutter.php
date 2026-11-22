<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recolour imported GA map sectors for contrast (matches the new ImportSitemap
 * default). Only rows still carrying the old import default are touched, so
 * admin-customised sectors are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('map_sectors')
            ->where('colour', '#d336c6')
            ->update(['colour' => '#a3e635', 'fill_opacity' => 0]);
    }

    public function down(): void
    {
        DB::table('map_sectors')
            ->where('colour', '#a3e635')
            ->update(['colour' => '#d336c6', 'fill_opacity' => 0.06]);
    }
};
