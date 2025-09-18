<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitors')) {
            return;
        }

        Schema::table('visitors', function (Blueprint $table) {
            if (!Schema::hasColumn('visitors', 'origin')) {
                $table->string('origin')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('visitors', 'check_in')) {
                $table->timestamp('check_in')->nullable()->after('visit_time');
            }
            if (!Schema::hasColumn('visitors', 'check_out')) {
                $table->timestamp('check_out')->nullable()->after('check_in');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('visitors')) {
            return;
        }
        Schema::table('visitors', function (Blueprint $table) {
            if (Schema::hasColumn('visitors', 'origin')) $table->dropColumn('origin');
            if (Schema::hasColumn('visitors', 'check_in')) $table->dropColumn('check_in');
            if (Schema::hasColumn('visitors', 'check_out')) $table->dropColumn('check_out');
        });
    }
};


