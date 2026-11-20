<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Entry;
use App\Models\Landmark;
use App\Models\Location;
use App\Models\MapSector;
use App\Models\User;
use App\Models\WorkTag;
use App\Models\WorkType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataset
{
    private const PREFIX = '[DEMO] ';
    private const USER_PREFIX = 'demo.';
    private const PERMIT_PREFIX = 'DEMO-';
    private const NOTE_PREFIX = 'DEMO: ';

    public static function seed(?string $password = null): array
    {
        return DB::transaction(function () use ($password) {
            self::clear();
            self::ensureWorkTypes();

            $generatedPassword = null;
            if (! $password) {
                $password = config('hwrt.demo_password');
            }
            if (! is_string($password) || strlen($password) < 12) {
                $generatedPassword = Str::random(20);
                $password = $generatedPassword;
            }

            $admin = self::user('admin', 'Demo Admin', 'admin', $password);
            $logger = self::user('logger', 'Demo Logger', 'user', $password);
            $viewer = self::user('viewer', 'Demo Viewer', 'user', $password);

            $north = self::area('North Operations', 'zone', self::polygon([
                [476150, 7718750], [476650, 7718750], [476650, 7719400], [476150, 7719400],
            ]), '#38bdf8', 0.08, 10);
            $south = self::area('South Operations', 'zone', self::polygon([
                [476150, 7718050], [476650, 7718050], [476650, 7718750], [476150, 7718750],
            ]), '#22c55e', 0.08, 20);

            self::markers();
            self::sectors();

            $locations = collect([
                self::location('GA-01 Laydown', 'DEMO-GA01', 476270, 7718460, $south, $admin, ['GA01', 'Laydown']),
                self::location('SITE C Workshop', 'DEMO-SITEC', 476430, 7718880, $north, $admin, ['Workshop']),
                self::location('GA-04 Tank Farm', 'DEMO-GA04', 476560, 7719180, $north, $admin, ['Tank Farm']),
                self::location('Access Road Gate 2', 'DEMO-GATE2', 476335, 7718250, $south, $admin, ['Gate 2']),
                self::location('PPA Batch Plant', 'DEMO-PPA', 476610, 7718640, $south, $admin, ['Batch Plant']),
            ]);

            $tags = collect([
                'SIMOPS' => WorkTag::create(['name' => 'DEMO: SIMOPS', 'active' => true]),
                'Night work' => WorkTag::create(['name' => 'DEMO: Night work', 'active' => true]),
                'High priority' => WorkTag::create(['name' => 'DEMO: High priority', 'active' => true]),
            ]);

            $type = fn (string $name) => WorkType::where('name', $name)->firstOrFail();
            $entries = [
                self::entry($locations[0], $type('Confined Space Entry'), $logger, $admin, 'open', now()->subMinutes(35), 'DEMO-1001', 'Vessel internal inspection with standby attendant.', ['High priority']),
                self::entry($locations[1], $type('Hot Work'), $logger, $admin, 'open', now()->subHours(2)->subMinutes(20), 'DEMO-1002', 'Grinding outside workshop door; fire watch posted.', ['SIMOPS']),
                self::entry($locations[2], $type('Working at Heights'), $logger, $admin, 'open', now()->subHours(5), 'DEMO-1003', 'Tank stair and platform inspection.'),
                self::entry($locations[3], $type('Excavation'), $viewer, $admin, 'open', now()->subDay(), 'DEMO-1004', 'Service trench adjacent to Gate 2.', ['Night work']),
                self::entry($locations[4], $type('Electrical Isolation'), $logger, $admin, 'open', now()->subHours(7), 'DEMO-1005', 'Batch plant MCC isolation and lockout.', ['High priority', 'SIMOPS']),
                self::entry($locations[0], $type('Inspection'), $viewer, $admin, 'pending', now()->addHours(2), 'DEMO-1006', 'Pre-start inspection for afternoon lift.'),
                self::entry($locations[1], $type('Other'), $logger, $admin, 'pending', now()->addHours(5), 'DEMO-1007', 'Specialist instrument calibration.', [], 'Instrument calibration'),
                self::entry($locations[2], $type('Hot Work'), $logger, $admin, 'closed', now()->subDays(2)->subHours(4), 'DEMO-0998', 'Completed welding repairs and closed after fire watch.'),
                self::entry($locations[3], $type('Excavation'), $logger, $admin, 'closed', now()->subDays(3)->subHours(2), 'DEMO-0997', 'Backfilled trial trench.'),
                self::entry($locations[4], $type('Confined Space Entry'), $viewer, $admin, 'closed', now()->subDays(4)->subHours(6), 'DEMO-0996', 'Mixer vessel clean-out completed.'),
                self::entry($locations[0], $type('Working at Heights'), $logger, $admin, 'cancelled', now()->subDays(5)->subHours(3), 'DEMO-0995', 'Lift postponed due to high wind.'),
            ];

            return [
                'users' => 3,
                'areas' => 2,
                'locations' => $locations->count(),
                'landmarks' => 5,
                'sectors' => 2,
                'work_tags' => $tags->count(),
                'entries' => count($entries),
                'generated_password' => $generatedPassword,
            ];
        });
    }

    public static function clear(): array
    {
        $userIds = User::where('username', 'like', self::USER_PREFIX.'%')
            ->orWhere('email', 'like', self::USER_PREFIX.'%@demo.invalid')
            ->pluck('id');
        $locationIds = Location::where('name', 'like', self::PREFIX.'%')
            ->orWhere('code', 'like', self::PERMIT_PREFIX.'%')
            ->pluck('id');
        $entryIds = Entry::query()
            ->where('permit_no', 'like', self::PERMIT_PREFIX.'%')
            ->orWhere('notes', 'like', self::NOTE_PREFIX.'%')
            ->orWhere('location_label', 'like', self::PREFIX.'%')
            ->when($locationIds->isNotEmpty(), fn ($q) => $q->orWhereIn('location_id', $locationIds))
            ->when($userIds->isNotEmpty(), fn ($q) => $q->orWhereIn('opened_by', $userIds)
                ->orWhereIn('closed_by', $userIds)
                ->orWhereIn('scheduled_by', $userIds))
            ->pluck('id');

        return [
            'entry_tags' => DB::table('entry_work_tag')->whereIn('entry_id', $entryIds)->delete(),
            'entry_events' => DB::table('entry_events')->whereIn('entry_id', $entryIds)->delete(),
            'entries' => Entry::whereIn('id', $entryIds)->delete(),
            'location_revisions' => DB::table('location_revisions')->whereIn('location_id', $locationIds)->delete(),
            'locations' => Location::whereIn('id', $locationIds)->delete(),
            'landmarks' => Landmark::where('source', 'demo')->orWhere('name', 'like', self::PREFIX.'%')->delete(),
            'sectors' => MapSector::where('name', 'like', self::PREFIX.'%')->delete(),
            'areas' => Area::where('source', 'demo')->orWhere('name', 'like', self::PREFIX.'%')->delete(),
            'work_tags' => WorkTag::where('name', 'like', 'DEMO:%')->delete(),
            'sessions' => DB::table('sessions')->whereIn('user_id', $userIds)->delete(),
            'users' => User::whereIn('id', $userIds)->delete(),
        ];
    }

    private static function ensureWorkTypes(): void
    {
        $types = [
            ['Confined Space Entry', '#d9534f', true, false, 'Crew / gas test / standby arrangements', false],
            ['Hot Work', '#f0ad4e', false, false, 'Fire watch / controls', false],
            ['Working at Heights', '#5bc0de', false, false, 'Access / fall protection / rescue considerations', false],
            ['Excavation', '#8a6d3b', false, false, 'Depth / services / controls', false],
            ['Electrical Isolation', '#6f42c1', false, false, 'Isolation / lockout details', false],
            ['Inspection', '#5cb85c', false, false, 'Inspection details', false],
            ['Other', '#777777', false, true, 'Describe the high risk work', true],
        ];

        foreach ($types as $i => [$name, $colour, $default, $requiresNote, $prompt, $other]) {
            WorkType::firstOrCreate(['name' => $name], [
                'colour' => $colour,
                'is_default' => $default,
                'requires_note' => $requiresNote,
                'notes_prompt' => $prompt,
                'is_other' => $other,
                'sort_order' => $i,
                'active' => true,
            ]);
        }
    }

    private static function user(string $suffix, string $name, string $role, string $password): User
    {
        return User::create([
            'name' => $name,
            'username' => self::USER_PREFIX.$suffix,
            'email' => self::USER_PREFIX.$suffix.'@demo.invalid',
            'password' => $password,
            'role' => $role,
            'active' => true,
            'must_change_password' => false,
        ]);
    }

    private static function polygon(array $ring): array
    {
        $ring[] = $ring[0];

        return ['type' => 'Polygon', 'coordinates' => [$ring]];
    }

    private static function area(string $name, string $kind, array $geometry, string $colour, float $opacity, int $order): Area
    {
        return Area::create([
            'name' => self::PREFIX.$name,
            'kind' => $kind,
            'geometry' => $geometry,
            'colour' => $colour,
            'fill_opacity' => $opacity,
            'source' => 'demo',
            'source_ref' => 'demo-dataset',
            'sort_order' => $order,
            'active' => true,
        ]);
    }

    private static function location(string $name, string $code, float $easting, float $northing, Area $area, User $creator, array $aliases): Location
    {
        return Location::create([
            'name' => self::PREFIX.$name,
            'code' => $code,
            'aliases' => $aliases,
            'easting' => $easting,
            'northing' => $northing,
            'area_id' => $area->id,
            'status' => 'active',
            'verified' => true,
            'created_by' => $creator->id,
        ]);
    }

    private static function markers(): void
    {
        $markers = [
            ['Main Access Road', 'roads', 'road_label', '#e2e8f0', 476220, 7718200, -18],
            ['Muster Point A', 'muster', 'muster_point', '#22c55e', 476185, 7718420, 0],
            ['Muster Point B', 'muster', 'muster_point', '#22c55e', 476640, 7719100, 0],
            ['Emergency Assembly', 'emergency', 'emergency', '#ef4444', 476205, 7719300, 0],
            ['Contractor Gate', 'landmark', 'landmark', '#ff5f1f', 476300, 7718060, 0],
        ];

        foreach ($markers as [$name, $category, $type, $colour, $easting, $northing, $rotation]) {
            Landmark::create([
                'name' => self::PREFIX.$name,
                'category' => $category,
                'marker_type' => $type,
                'colour' => $colour,
                'easting' => $easting,
                'northing' => $northing,
                'label_visible' => true,
                'rotation_deg' => $rotation,
                'notes' => self::NOTE_PREFIX.'sample map overlay',
                'source' => 'demo',
                'active' => true,
            ]);
        }
    }

    private static function sectors(): void
    {
        $sectors = [
            ['Sector A', '#38bdf8', 0.10, 10, [[476180, 7718780], [476420, 7718780], [476420, 7719020], [476180, 7719020]]],
            ['Sector B', '#f97316', 0.12, 20, [[476180, 7718120], [476410, 7718120], [476410, 7718400], [476180, 7718400]]],
        ];

        foreach ($sectors as [$name, $colour, $opacity, $order, $ring]) {
            MapSector::create([
                'name' => self::PREFIX.$name,
                'colour' => $colour,
                'fill_opacity' => $opacity,
                'geometry' => self::polygon($ring),
                'notes' => self::NOTE_PREFIX.'sample sector',
                'active' => true,
                'sort_order' => $order,
            ]);
        }
    }

    private static function entry(Location $location, WorkType $type, User $openedBy, User $scheduledBy, string $status, Carbon $openedAt, string $permit, string $notes, array $tagNames = [], ?string $other = null): Entry
    {
        $planned = $status === 'pending';
        $closed = in_array($status, ['closed', 'cancelled'], true);
        $entry = Entry::create([
            'location_id' => $location->id,
            'location_label' => $location->name,
            'easting' => $location->easting,
            'northing' => $location->northing,
            'area_id' => $location->area_id,
            'work_type_id' => $type->id,
            'other_description' => $other,
            'notes' => self::NOTE_PREFIX.$notes,
            'permit_no' => $permit,
            'reported_by' => $openedBy->name,
            'status' => $status,
            'planned_start_at' => $planned ? $openedAt : null,
            'scheduled_by' => $planned ? $scheduledBy->id : null,
            'opened_at' => $openedAt,
            'opened_by' => $openedBy->id,
            'closed_at' => $closed ? $openedAt->copy()->addHours(3) : null,
            'closed_by' => $closed ? $scheduledBy->id : null,
            'close_note' => $closed ? self::NOTE_PREFIX.($status === 'closed' ? 'Work completed.' : 'Work cancelled.') : null,
            'submission_key' => Str::uuid(),
        ]);
        $entry->events()->create([
            'event' => $status === 'pending' ? 'scheduled' : 'created',
            'actor_id' => $openedBy->id,
            'changes' => ['demo' => true],
            'occurred_at' => $openedAt,
        ]);
        if ($closed) {
            $entry->events()->create([
                'event' => $status,
                'actor_id' => $scheduledBy->id,
                'changes' => ['demo' => true],
                'occurred_at' => $entry->closed_at,
            ]);
        }
        if ($tagNames) {
            $entry->workTags()->sync(WorkTag::whereIn('name', array_map(fn ($name) => 'DEMO: '.$name, $tagNames))->pluck('id'));
        }

        $location->increment('usage_count');
        $location->forceFill(['last_used_at' => $openedAt])->save();

        return $entry;
    }
}
