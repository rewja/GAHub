<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column exists, if not add it
        if (!Schema::hasColumn('todos', 'evidence_path')) {
            Schema::table('todos', function (Blueprint $table) {
                $table->string('evidence_path')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('todos', 'evidence_path')) {
            Schema::table('todos', function (Blueprint $table) {
                $table->dropColumn('evidence_path');
            });
        }
    }
};

