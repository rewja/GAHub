<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitors')) {
            return;
        }

        // If legacy column exists, make it nullable to avoid insert errors
        if (Schema::hasColumn('visitors', 'person_to_meet')) {
            // Use raw SQL to avoid requiring doctrine/dbal for column modification
            DB::statement("ALTER TABLE visitors MODIFY person_to_meet VARCHAR(191) NULL");
        }

        // Ensure new column exists as nullable (our code uses meet_with)
        if (!Schema::hasColumn('visitors', 'meet_with')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->string('meet_with')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('visitors')) {
            return;
        }
        // No destructive rollback; keep schema compatible
    }
};


