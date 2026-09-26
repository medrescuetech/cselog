<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bootstrapUsername = strtolower(trim((string) config('hwrt.bootstrap_admin.username', 'admin')));
        if (str_contains($bootstrapUsername, '@')) {
            $bootstrapUsername = (string) strtok($bootstrapUsername, '@');
        }

        foreach (DB::table('users')->where('username', $bootstrapUsername)->orderBy('id')->get(['id']) as $user) {
            $candidate = "legacy-{$bootstrapUsername}-{$user->id}";
            $suffix = 2;
            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = "legacy-{$bootstrapUsername}-{$user->id}-".($suffix++);
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }
    }

    public function down(): void
    {
        // Username reservation is intentionally not reversed; restore the pre-update database backup.
    }
};
