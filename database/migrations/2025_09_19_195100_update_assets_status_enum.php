<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Extend asset status enum to include procurement
        DB::statement("ALTER TABLE assets MODIFY status ENUM('procurement','not_received','received','needs_repair','needs_replacement') NOT NULL DEFAULT 'procurement'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE assets MODIFY status ENUM('not_received','received','needs_repair','needs_replacement') NOT NULL DEFAULT 'not_received'");
    }
};








