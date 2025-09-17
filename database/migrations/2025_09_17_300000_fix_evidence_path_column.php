<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if column exists, if not add it
        if (!Schema::hasColumn('todos', 'evidence_path')) {
            Schema::table('todos', function (Blueprint $table) {
                $table->string('evidence_path')->nullable()->after('status');
            });
        }

        // Also ensure the column is properly set in the database
        DB::statement("ALTER TABLE todos MODIFY COLUMN evidence_path VARCHAR(255) NULL");
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            if (Schema::hasColumn('todos', 'evidence_path')) {
                $table->dropColumn('evidence_path');
            }
        });
    }
};
