<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If the table already exists (created by earlier migration), skip.
        if (Schema::hasTable('visitors')) {
            return;
        }

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('meet_with')->nullable();
            $table->string('purpose')->nullable();
            $table->timestamp('visit_time')->nullable();
            $table->string('ktp_image_path')->nullable();
            $table->json('ktp_ocr')->nullable();
            $table->string('face_image_path')->nullable();
            $table->boolean('face_verified')->default(false);
            $table->string('status')->default('pending'); // pending, checked_in, checked_out
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};


