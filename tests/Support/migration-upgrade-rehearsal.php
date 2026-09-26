<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (app()->environment() !== 'testing'
    || ! in_array(config('database.default'), ['mysql', 'mariadb'], true)
    || config('database.connections.'.config('database.default').'.database') !== 'hwrt') {
    fwrite(STDERR, "Migration rehearsal is restricted to the CI testing database named 'hwrt'.\n");
    exit(2);
}

$mode = $argv[1] ?? '';
$legacyPassword = 'LegacyAdminPassword123!';

if ($mode === 'prepare') {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Legacy Admin',
        'email' => 'admin@example.com',
        'email_verified_at' => null,
        'password' => Hash::make($legacyPassword),
        'remember_token' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'role' => 'admin',
        'active' => true,
    ]);

    $workTypeId = DB::table('work_types')->insertGetId([
        'name' => 'Other',
        'colour' => '#777777',
        'is_default' => false,
        'requires_note' => false,
        'sort_order' => 0,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('entries')->insert([
        'location_id' => null,
        'location_label' => 'Legacy location',
        'easting' => 476100,
        'northing' => 7718100,
        'area_id' => null,
        'work_type_id' => $workTypeId,
        'notes' => 'Legacy notes',
        'permit_no' => 'LEGACY-1',
        'reported_by' => 'Legacy reporter',
        'status' => 'open',
        'opened_at' => now(),
        'opened_by' => $userId,
        'closed_at' => null,
        'closed_by' => null,
        'close_note' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    fwrite(STDOUT, "Prepared a V1-like database containing an existing Admin and HRW record.\n");
    exit(0);
}

if ($mode === 'verify') {
    $legacy = User::query()->where('email', 'admin@example.com')->first();
    if (! $legacy
        || $legacy->username !== 'legacy-admin-'.$legacy->id
        || ! $legacy->isAdmin()
        || ! Hash::check($legacyPassword, $legacy->password)) {
        fwrite(STDERR, "The existing Admin username, role, email or password did not survive the upgrade.\n");
        exit(1);
    }

    $entry = DB::table('entries')->where('permit_no', 'LEGACY-1')->first();
    if (! $entry || $entry->hrw_ref !== 'HRW-000001' || $entry->notes !== 'Legacy notes') {
        fwrite(STDERR, "The existing HRW record or its backfilled reference did not survive the upgrade.\n");
        exit(1);
    }

    $other = DB::table('work_types')->where('name', 'Other')->first();
    if (! $other || ! $other->is_other || ! $other->requires_note) {
        fwrite(STDERR, "The existing Other work type did not migrate correctly.\n");
        exit(1);
    }

    $bootstrap = User::query()->where('username', 'admin')->first();
    if (! $bootstrap
        || ! $bootstrap->isAdmin()
        || ! $bootstrap->active
        || ! $bootstrap->must_change_password
        || ! Hash::check('admin', $bootstrap->password)) {
        fwrite(STDERR, "The new bootstrap Admin was not provisioned with required password rotation.\n");
        exit(1);
    }

    fwrite(STDOUT, "Verified the V1-to-V2 upgrade preserved legacy data and created a separate rotatable Admin.\n");
    exit(0);
}

fwrite(STDERR, "Usage: php tests/Support/migration-upgrade-rehearsal.php prepare|verify\n");
exit(2);
