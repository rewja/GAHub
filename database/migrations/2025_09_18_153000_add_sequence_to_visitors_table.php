<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitors')) return;
        Schema::table('visitors', function (Blueprint $table) {
            if (!Schema::hasColumn('visitors', 'sequence')) {
                $table->unsignedInteger('sequence')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('visitors')) return;
        Schema::table('visitors', function (Blueprint $table) {
            if (Schema::hasColumn('visitors', 'sequence')) {
                $table->dropColumn('sequence');
            }
        });
    }
};


