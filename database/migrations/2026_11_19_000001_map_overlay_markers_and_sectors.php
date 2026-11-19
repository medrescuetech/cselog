<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->string('marker_type', 40)->default('landmark')->after('category');
            $table->string('colour', 16)->default('#ff5f1f')->after('marker_type');
            $table->boolean('label_visible')->default(false)->after('colour');
            $table->smallInteger('rotation_deg')->default(0)->after('label_visible');
            $table->text('notes')->nullable()->after('rotation_deg');
        });

        Schema::create('map_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('colour', 16)->default('#38bdf8');
            $table->decimal('fill_opacity', 3, 2)->default(0.08);
            $table->json('geometry');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_sectors');

        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn(['marker_type', 'colour', 'label_visible', 'rotation_deg', 'notes']);
        });
    }
};
