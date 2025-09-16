<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing data to new status
        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('not_started', 'in_progress', 'checking', 'completed') DEFAULT 'not_started'");

        // Update existing records
        DB::table('todos')->where('status', 'pending')->update(['status' => 'not_started']);
        DB::table('todos')->where('status', 'done')->update(['status' => 'completed']);
        DB::table('todos')->where('status', 'checked')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        // Revert to previous enum
        DB::statement("ALTER TABLE todos MODIFY COLUMN status ENUM('pending', 'in_progress', 'done', 'checked') DEFAULT 'pending'");

        // Revert existing records
        DB::table('todos')->where('status', 'not_started')->update(['status' => 'pending']);
        DB::table('todos')->where('status', 'completed')->update(['status' => 'done']);
    }
};
