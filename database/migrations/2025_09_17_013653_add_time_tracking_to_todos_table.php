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
        Schema::table('todos', function (Blueprint $table) {
            // Tanggal yang dijadwalkan untuk dikerjakan
            $table->date('scheduled_date')->nullable()->after('due_date');

            // Waktu mulai mengerjakan
            $table->timestamp('started_at')->nullable()->after('scheduled_date');

            // Waktu submit untuk pengecekan
            $table->timestamp('submitted_at')->nullable()->after('started_at');

            // Total waktu pengerjaan (dalam menit)
            $table->integer('total_work_time')->nullable()->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_date',
                'started_at',
                'submitted_at',
                'total_work_time'
            ]);
        });
    }
};
