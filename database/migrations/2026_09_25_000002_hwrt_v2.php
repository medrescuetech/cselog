<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->nullable()->unique()->after('name');
        });

        // V2 permission model: User or Admin.
        DB::table('users')->where('role', '!=', 'admin')->update(['role' => 'user']);

        $used = [];
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $base = strtolower((string) strtok((string) $user->email, '@'));
            $base = preg_replace('/[^a-z0-9._-]+/', '.', $base) ?: 'user'.$user->id;
            $candidate = $base;
            $n = 2;
            while (isset($used[$candidate])) {
                $candidate = $base.$n++;
            }
            $used[$candidate] = true;
            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('work_types', function (Blueprint $table) {
            $table->string('notes_prompt', 255)->nullable()->after('requires_note');
            $table->boolean('is_other')->default(false)->after('notes_prompt');
        });

        DB::table('work_types')
            ->whereRaw('LOWER(name) = ?', ['other'])
            ->update(['is_other' => true, 'requires_note' => true]);

        Schema::table('entries', function (Blueprint $table) {
            $table->string('hrw_ref', 32)->nullable()->unique()->after('id');
            $table->string('other_description', 160)->nullable()->after('work_type_id');
            $table->dateTime('planned_start_at')->nullable()->after('status');
            $table->foreignId('scheduled_by')->nullable()->after('planned_start_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('document_path', 500)->nullable();
            $table->string('document_name', 255)->nullable();
            $table->dateTime('document_uploaded_at')->nullable();
            $table->foreignId('document_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('entries')->orderBy('id')->get(['id']) as $entry) {
            DB::table('entries')->where('id', $entry->id)->update([
                'hrw_ref' => 'HRW-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT),
            ]);
        }

        DB::table('settings')->insertOrIgnore([
            ['key' => 'appearance.theme', 'value' => 'dark', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'logbook.days', 'value' => '7', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_uploaded_by');
            $table->dropColumn(['document_path', 'document_name', 'document_uploaded_at']);
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_by');
            $table->dropUnique(['hrw_ref']);
            $table->dropColumn(['hrw_ref', 'other_description', 'planned_start_at']);
        });

        Schema::table('work_types', function (Blueprint $table) {
            $table->dropColumn(['notes_prompt', 'is_other']);
        });

        foreach (DB::table('users')->whereNull('email')->get(['id', 'username']) as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'email' => ($user->username ?: 'user'.$user->id).'@local.invalid',
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });

        Schema::dropIfExists('settings');
    }
};
