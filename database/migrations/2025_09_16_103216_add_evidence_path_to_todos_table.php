<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('todos', 'evidence_path')) {
            Schema::table('todos', function (Blueprint $table) {
                $table->string('evidence_path')->nullable()->after('notes');
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
