<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core CSEM schema (docs/03-data-model.md + deltas in docs/11-build-plan.md).
 * All coordinates are MGA Zone 50 / EPSG:28350 metres (easting, northing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('logger')->after('password'); // viewer|logger|supervisor|admin
            $table->boolean('active')->default(true)->after('role');
        });

        Schema::create('work_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('colour', 16)->default('#d9534f');
            $table->boolean('is_default')->default(false);
            $table->boolean('requires_note')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 60)->nullable();      // boundary | zone | lease | infrastructure
            $table->json('geometry');                    // GeoJSON Polygon/MultiPolygon, EPSG:28350
            $table->string('colour', 16)->nullable();
            $table->decimal('fill_opacity', 3, 2)->default(0.15);
            $table->string('source', 255)->nullable();  // file / service it came from
            $table->string('source_ref', 120)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'name']);
        });

        Schema::create('landmarks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 60)->nullable();
            $table->decimal('easting', 10, 3);
            $table->decimal('northing', 11, 3);
            $table->string('source', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['category', 'name']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->string('code', 60)->nullable();
            $table->decimal('easting', 10, 3);
            $table->decimal('northing', 11, 3);
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('status', 20)->default('active'); // active | archived
            $table->boolean('verified')->default(false);
            $table->foreignId('merged_into_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('location_label', 160);       // snapshot of the name as logged
            $table->decimal('easting', 10, 3);            // pin as logged — never depends on catalogue
            $table->decimal('northing', 11, 3);
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('work_type_id')->constrained('work_types');
            $table->text('notes')->nullable();
            $table->string('permit_no', 60)->nullable();
            $table->string('reported_by', 120)->nullable();
            $table->string('status', 20)->default('open'); // open | closed | cancelled
            $table->dateTime('opened_at');
            $table->foreignId('opened_by')->constrained('users');
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->text('close_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'opened_at']);
            $table->index('opened_at');
        });

        Schema::create('entry_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->string('event', 20); // created | updated | closed | reopened | cancelled
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_ip', 45)->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['entry_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_events');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('landmarks');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('work_types');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};
