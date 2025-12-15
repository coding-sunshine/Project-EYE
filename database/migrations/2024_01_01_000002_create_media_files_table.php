<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();

            // Media type discriminator (STI) - accepts any value
            $table->string('media_type')->index();

            // Common file fields
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable(); // bytes

            // AI/ML descriptions (shared across all types)
            $table->text('description')->nullable();
            $table->text('detailed_description')->nullable();
            $table->json('meta_tags')->nullable();

            // AI features (shared)
            $table->integer('face_count')->default(0);
            $table->json('face_encodings')->nullable();

            // Image-specific fields (nullable for other types)
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->jsonb('exif_data')->nullable();
            $table->string('camera_make')->nullable();
            $table->string('camera_model')->nullable();
            $table->string('lens_model')->nullable();
            $table->timestamp('date_taken')->nullable();
            $table->string('exposure_time')->nullable();
            $table->string('f_number')->nullable();
            $table->integer('iso')->nullable();
            $table->decimal('focal_length', 8, 2)->nullable();

            // Video/Audio-specific fields
            $table->integer('duration_seconds')->nullable(); // Video & Audio
            $table->string('video_codec')->nullable(); // Video
            $table->string('audio_codec')->nullable(); // Video & Audio
            $table->integer('bitrate')->nullable(); // Video & Audio
            $table->decimal('fps', 8, 2)->nullable(); // Video
            $table->string('resolution')->nullable(); // Video (e.g., "1920x1080")

            // Document-specific fields
            $table->integer('page_count')->nullable(); // PDF/Documents
            $table->text('extracted_text')->nullable(); // Searchable text from documents/video transcription

            // GPS data (images & videos)
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->string('gps_location_name')->nullable();

            // Gallery features (shared)
            $table->boolean('is_favorite')->default(false);
            $table->integer('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->jsonb('edit_history')->nullable();
            $table->string('album')->nullable();

            // Processing status (shared)
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending')
                  ->index();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->integer('processing_attempts')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_favorite', 'media_type']);
            $table->index('created_at');
        });

        // Only add vector column and index for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Add vector column using raw SQL (pgvector type)
            // CLIP ViT-B/32 produces 512-dimensional embeddings
            DB::statement('ALTER TABLE media_files ADD COLUMN embedding vector(512)');

            // Create HNSW index for fast similarity search
            // HNSW works better than IVFFlat for smaller datasets
            DB::statement('CREATE INDEX media_files_embedding_idx ON media_files USING hnsw (embedding vector_cosine_ops)');

            // Full-text search index for descriptions and extracted text
            DB::statement('CREATE INDEX media_files_text_search_idx ON media_files USING gin(to_tsvector(\'english\', coalesce(description, \'\') || \' \' || coalesce(detailed_description, \'\') || \' \' || coalesce(extracted_text, \'\')))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
