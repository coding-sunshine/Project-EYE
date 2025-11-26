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
        Schema::table('media_files', function (Blueprint $table) {
            $table->integer('upload_progress')->default(0)->after('processing_status');
            $table->timestamp('upload_started_at')->nullable()->after('upload_progress');
            $table->timestamp('upload_completed_at')->nullable()->after('upload_started_at');
            $table->string('processing_stage')->nullable()->after('upload_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn(['upload_progress', 'upload_started_at', 'upload_completed_at', 'processing_stage']);
        });
    }
};
