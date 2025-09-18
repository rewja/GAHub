<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitors')) {
            return;
        }

        // Some older schemas had NOT NULL check_in/check_out without defaults
        if (Schema::hasColumn('visitors', 'check_in')) {
            DB::statement('ALTER TABLE visitors MODIFY check_in TIMESTAMP NULL DEFAULT NULL');
        }
        if (Schema::hasColumn('visitors', 'check_out')) {
            DB::statement('ALTER TABLE visitors MODIFY check_out TIMESTAMP NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        // no-op safe rollback
    }
};


