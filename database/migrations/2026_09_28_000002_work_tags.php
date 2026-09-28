<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('entry_work_tag', function (Blueprint $table) {
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('work_tag_id')->constrained('work_tags');
            $table->primary(['entry_id', 'work_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_work_tag');
        Schema::dropIfExists('work_tags');
    }
};
