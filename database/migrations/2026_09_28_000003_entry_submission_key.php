<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable();
            $table->unique(['opened_by', 'submission_key']);
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropUnique(['opened_by', 'submission_key']);
            $table->dropColumn('submission_key');
        });
    }
};
