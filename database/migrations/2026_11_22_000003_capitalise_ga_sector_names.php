<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The sitemap importer title-cases names ("GA8 NORTH" -> "Ga8 North"). GA sector
 * names keep the capital A: normalise the stored rows and match the importer fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('map_sectors')) {
            return;
        }
        DB::table('map_sectors')
            ->whereRaw("name REGEXP '^Ga[0-9]'")
            ->update(['name' => DB::raw("CONCAT('GA', SUBSTRING(name, 3))")]);
    }

    public function down(): void
    {
        DB::table('map_sectors')
            ->whereRaw("name REGEXP '^GA[0-9]'")
            ->update(['name' => DB::raw("CONCAT('Ga', SUBSTRING(name, 3))")]);
    }
};
