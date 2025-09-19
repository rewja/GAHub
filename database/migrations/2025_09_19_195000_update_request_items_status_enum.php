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
        // Extend status enum to include procurement and not_received
        DB::statement("ALTER TABLE request_items MODIFY status ENUM('pending','approved','rejected','procurement','not_received','purchased') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum definition
        DB::statement("ALTER TABLE request_items MODIFY status ENUM('pending','approved','rejected','purchased') NOT NULL DEFAULT 'pending'");
    }
};








