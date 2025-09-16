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
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // siapa yang buat
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'done', 'checked'])->default('pending');
            // pending    = baru dibuat oleh user
            // in_progress = lagi dikerjakan
            // done       = selesai dikerjakan (oleh user)
            // checked    = sudah dicek/approve oleh GA

            $table->foreignId('checked_by')->nullable()->constrained('users')->onDelete('set null'); // GA yang ngecek
            $table->text('notes')->nullable(); // catatan dari GA
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
};
