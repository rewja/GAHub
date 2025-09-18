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
            // Modify the status column to include 'reworked' as a valid value
            $table->enum('status', ['not_started', 'in_progress', 'checking', 'evaluating', 'reworked', 'completed'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            // Revert back to original status values
            $table->enum('status', ['not_started', 'in_progress', 'checking', 'evaluating', 'completed'])->change();
        });
    }
};
