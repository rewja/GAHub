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
        if (!Schema::hasColumn('todos', 'checker_display')) {
            Schema::table('todos', function (Blueprint $table) {
                $table->string('checker_display')->nullable()->after('checked_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('todos', 'checker_display')) {
            Schema::table('todos', function (Blueprint $table) {
                $table->dropColumn('checker_display');
            });
        }
    }
};
