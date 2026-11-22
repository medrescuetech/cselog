<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The sitemap importer title-cases names ("GA8 NORTH" -> "Ga8 North"). GA names keep
 * the capital A: normalise the stored rows and match the importer fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['areas' => 'kind', 'landmarks' => 'category'] as $table => $column) {
            DB::table($table)
                ->where($column, 'ga')
                ->whereRaw("name REGEXP '^Ga[0-9]'")
                ->update(['name' => DB::raw("CONCAT('GA', SUBSTRING(name, 3))")]);
        }
    }

    public function down(): void
    {
        foreach (['areas' => 'kind', 'landmarks' => 'category'] as $table => $column) {
            DB::table($table)
                ->where($column, 'ga')
                ->whereRaw("name REGEXP '^GA[0-9]'")
                ->update(['name' => DB::raw("CONCAT('Ga', SUBSTRING(name, 3))")]);
        }
    }
};
