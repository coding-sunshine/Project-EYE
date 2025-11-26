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
            // Archive-specific columns
            $table->string('compression_type')->nullable()->after('processing_error'); // zip, rar, tar, 7z, gzip
            $table->bigInteger('uncompressed_size')->nullable()->after('compression_type'); // Total uncompressed size in bytes
            $table->integer('file_count')->nullable()->after('uncompressed_size'); // Number of files in archive
            $table->json('file_list')->nullable()->after('file_count'); // List of files in archive with metadata
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn(['compression_type', 'uncompressed_size', 'file_count', 'file_list']);
        });
    }
};
