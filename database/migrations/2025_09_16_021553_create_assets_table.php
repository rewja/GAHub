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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_items_id')->constrained('request_items')->onDelete('cascade');
            $table->string('asset_code', 50)->unique();
            $table->string('category', 100);
            $table->enum('status', ['not_received', 'received', 'needs_repair', 'needs_replacement'])->default('not_received');
            $table->dateTime('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
