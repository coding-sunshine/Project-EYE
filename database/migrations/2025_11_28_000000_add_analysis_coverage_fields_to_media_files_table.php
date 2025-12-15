<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds maximum analysis coverage fields:
     * - Object detection results (Florence-2 <OD> task)
     * - Scene classification (via Ollama)
     * - Dominant colors (K-means clustering)
     * - Image quality metrics (OpenCV)
     * - Duplicate detection hashes (perceptual hashing)
     */
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // Object Detection - stores bboxes and labels from Florence-2 <OD> task
            // Format: {"labels": ["person", "dog"], "bboxes": [[x1,y1,x2,y2], ...], "label_counts": {"person": 2, "dog": 1}}
            $table->jsonb('objects_detected')->nullable()->after('face_encodings');

            // Scene Classification - hierarchical scene categorization via Ollama
            // Format: {"environment": "outdoor", "setting": "beach", "context": ["vacation", "relaxation"], "confidence": 0.92}
            $table->jsonb('scene_classification')->nullable()->after('objects_detected');

            // Dominant Colors - top colors from K-means clustering
            // Format: [{"hex": "#ff8000", "rgb": [255,128,0], "name": "orange", "percentage": 35.2}, ...]
            $table->jsonb('dominant_colors')->nullable()->after('scene_classification');

            // Image Quality Metrics - OpenCV-based quality assessment
            // Format: {"overall_score": 0.85, "sharpness": 0.9, "brightness": 0.6, "contrast": 0.7, "issues": {"is_blurry": false}}
            $table->jsonb('image_quality')->nullable()->after('dominant_colors');

            // Quality Tier - derived from image_quality for easy filtering
            // Values: excellent, good, fair, poor
            $table->string('quality_tier', 20)->nullable()->after('image_quality');

            // Perceptual Hashes for duplicate detection
            // pHash (perceptual) - best for similar images
            $table->string('phash', 16)->nullable()->after('quality_tier');
            // dHash (difference) - good for crops/edits
            $table->string('dhash', 16)->nullable()->after('phash');
        });

        // Add indexes for efficient querying (PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            // GIN indexes for JSONB columns (supports @> containment queries)
            DB::statement('CREATE INDEX media_files_objects_gin ON media_files USING gin(objects_detected)');
            DB::statement('CREATE INDEX media_files_scene_gin ON media_files USING gin(scene_classification)');
            DB::statement('CREATE INDEX media_files_colors_gin ON media_files USING gin(dominant_colors)');

            // B-tree indexes for scalar columns
            DB::statement('CREATE INDEX media_files_quality_tier_idx ON media_files(quality_tier)');
            DB::statement('CREATE INDEX media_files_phash_idx ON media_files(phash)');
            DB::statement('CREATE INDEX media_files_dhash_idx ON media_files(dhash)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes first (PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS media_files_objects_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_scene_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_colors_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_quality_tier_idx');
            DB::statement('DROP INDEX IF EXISTS media_files_phash_idx');
            DB::statement('DROP INDEX IF EXISTS media_files_dhash_idx');
        }

        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn([
                'objects_detected',
                'scene_classification',
                'dominant_colors',
                'image_quality',
                'quality_tier',
                'phash',
                'dhash'
            ]);
        });
    }
};
