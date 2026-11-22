<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Map display clean-up: brighter GA borders, and landmark categories that match
 * the current ImportSitemap scheme (kind = category) so imported areas, roads and
 * named infrastructure can be styled/toggled separately on the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('areas')->where('kind', 'ga')->update(['colour' => '#a3e635']);

        $kinds = DB::table('areas')->whereIn('kind', ['ga', 'road', 'infrastructure'])->pluck('kind', 'name');
        foreach (DB::table('landmarks')->where('category', 'infrastructure')->get(['id', 'name', 'source']) as $lm) {
            if (isset($kinds[$lm->name]) && $lm->source && $lm->source !== 'manual') {
                DB::table('landmarks')->where('id', $lm->id)->update(['category' => $kinds[$lm->name]]);
            }
        }
    }

    public function down(): void
    {
        DB::table('areas')->where('kind', 'ga')->update(['colour' => '#d336c6']);
        DB::table('landmarks')
            ->whereIn('category', ['ga', 'road'])
            ->update(['category' => 'infrastructure']);
    }
};
