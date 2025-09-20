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
        // Add 'received' to request_items.status enum
        DB::statement("ALTER TABLE request_items MODIFY status ENUM('pending','approved','rejected','procurement','not_received','purchased','completed','received') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE request_items MODIFY status ENUM('pending','approved','rejected','procurement','not_received','purchased','completed') NOT NULL DEFAULT 'pending'");
    }
};









