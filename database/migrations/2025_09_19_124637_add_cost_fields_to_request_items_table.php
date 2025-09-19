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
        Schema::table('request_items', function (Blueprint $table) {
            $table->decimal('estimated_cost', 15, 2)->nullable()->after('quantity');
            $table->decimal('actual_cost', 15, 2)->nullable()->after('estimated_cost');
            $table->string('category', 100)->nullable()->after('actual_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn(['estimated_cost', 'actual_cost', 'category']);
        });
    }
};
