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
            // Document intelligence fields
            $table->string('document_type', 50)->nullable()->after('extracted_text');
            $table->decimal('classification_confidence', 3, 2)->nullable()->after('document_type');
            $table->jsonb('entities')->nullable()->after('classification_confidence');

            // Add index for document_type for faster filtering
            $table->index('document_type');

            // Add GIN index for entities JSONB column for faster querying
            $table->rawIndex('entities', 'media_files_entities_gin', 'gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('media_files_document_type_index');
            $table->dropIndex('media_files_entities_gin');

            // Drop columns
            $table->dropColumn(['document_type', 'classification_confidence', 'entities']);
        });
    }
};
