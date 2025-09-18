<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitors')) {
            return; // created by other migration
        }

        Schema::table('visitors', function (Blueprint $table) {
            if (!Schema::hasColumn('visitors', 'meet_with')) {
                $table->string('meet_with')->nullable()->after('name');
            }
            if (!Schema::hasColumn('visitors', 'purpose')) {
                $table->string('purpose')->nullable()->after('meet_with');
            }
            if (!Schema::hasColumn('visitors', 'visit_time')) {
                $table->timestamp('visit_time')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('visitors', 'ktp_image_path')) {
                $table->string('ktp_image_path')->nullable()->after('visit_time');
            }
            if (!Schema::hasColumn('visitors', 'ktp_ocr')) {
                $table->json('ktp_ocr')->nullable()->after('ktp_image_path');
            }
            if (!Schema::hasColumn('visitors', 'face_image_path')) {
                $table->string('face_image_path')->nullable()->after('ktp_ocr');
            }
            if (!Schema::hasColumn('visitors', 'face_verified')) {
                $table->boolean('face_verified')->default(false)->after('face_image_path');
            }
            if (!Schema::hasColumn('visitors', 'status')) {
                $table->string('status')->default('pending')->after('face_verified');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('visitors')) {
            return;
        }
        Schema::table('visitors', function (Blueprint $table) {
            if (Schema::hasColumn('visitors', 'meet_with')) $table->dropColumn('meet_with');
            if (Schema::hasColumn('visitors', 'purpose')) $table->dropColumn('purpose');
            if (Schema::hasColumn('visitors', 'visit_time')) $table->dropColumn('visit_time');
            if (Schema::hasColumn('visitors', 'ktp_image_path')) $table->dropColumn('ktp_image_path');
            if (Schema::hasColumn('visitors', 'ktp_ocr')) $table->dropColumn('ktp_ocr');
            if (Schema::hasColumn('visitors', 'face_image_path')) $table->dropColumn('face_image_path');
            if (Schema::hasColumn('visitors', 'face_verified')) $table->dropColumn('face_verified');
            if (Schema::hasColumn('visitors', 'status')) $table->dropColumn('status');
        });
    }
};


