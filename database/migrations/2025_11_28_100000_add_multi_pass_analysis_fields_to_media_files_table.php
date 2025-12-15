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
     * Adds comprehensive multi-pass VLM analysis fields:
     * - Pass 1: Content & Scene Analysis (subjects, objects, setting, composition)
     * - Pass 2: People & Emotion Analysis (demographics, emotions, interactions)
     * - Pass 3: Quality & Technical Analysis (focus, exposure, lighting, color)
     * - Pass 4: Context & Metadata Generation (tags, albums, keywords, occasion)
     * - Combined: Merged analysis with summary and suggestions
     */
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // Pass 1: Content & Scene Analysis
            // Format: {"main_subjects": [...], "objects": [...], "setting": {...}, "environment": {...}, "activities": [...], "composition": {...}, "description": "..."}
            $table->jsonb('content_analysis')->nullable()->after('image_quality');

            // Pass 2: People & Emotion Analysis
            // Format: {"people_count": 0, "has_people": false, "people": [{position, age_range, gender, emotion, pose, attire}], "group_dynamics": {...}, "body_language": {...}}
            $table->jsonb('people_analysis')->nullable()->after('content_analysis');

            // Pass 3: Quality & Technical Analysis
            // Format: {"overall_quality": {score, tier, summary}, "focus": {...}, "exposure": {...}, "lighting": {...}, "color": {...}, "composition": {...}, "technical_issues": [...]}
            $table->jsonb('quality_analysis')->nullable()->after('people_analysis');

            // Pass 4: Context & Metadata Generation
            // Format: {"temporal": {...}, "occasion": {...}, "brands_products": {...}, "location_inference": {...}, "suggested_tags": [...], "suggested_albums": [...], "searchable_keywords": [...]}
            $table->jsonb('context_analysis')->nullable()->after('quality_analysis');

            // Combined Analysis - Merged results from all passes with summary
            // Format: {"summary": {...}, "tags": [...], "searchable_keywords": [...], "suggested_albums": [...], "quality_tier": "...", "has_people": bool, "people_count": int, "is_screenshot": bool, "is_selfie": bool}
            $table->jsonb('combined_analysis')->nullable()->after('context_analysis');

            // Analysis Mode - which analysis mode was used
            // Values: quick (single-pass ~8s), comprehensive (4-pass ~40-60s)
            $table->string('analysis_mode', 20)->nullable()->after('combined_analysis');

            // Analysis Passes Completed - tracking for partial completions
            // Range: 0-4 for comprehensive mode, 0-1 for quick mode
            $table->unsignedTinyInteger('analysis_passes_completed')->default(0)->after('analysis_mode');

            // Analysis Passes Failed - tracking failed passes
            $table->unsignedTinyInteger('analysis_passes_failed')->default(0)->after('analysis_passes_completed');

            // Analysis Duration - time taken for full analysis in seconds
            $table->decimal('analysis_duration_seconds', 8, 2)->nullable()->after('analysis_passes_failed');

            // Analysis Timestamp - when the multi-pass analysis was performed
            $table->timestamp('analysis_completed_at')->nullable()->after('analysis_duration_seconds');
        });

        // Add indexes for efficient querying (PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            // GIN indexes for JSONB columns (supports @> containment and text search)
            DB::statement('CREATE INDEX media_files_content_analysis_gin ON media_files USING gin(content_analysis)');
            DB::statement('CREATE INDEX media_files_people_analysis_gin ON media_files USING gin(people_analysis)');
            DB::statement('CREATE INDEX media_files_quality_analysis_gin ON media_files USING gin(quality_analysis)');
            DB::statement('CREATE INDEX media_files_context_analysis_gin ON media_files USING gin(context_analysis)');
            DB::statement('CREATE INDEX media_files_combined_analysis_gin ON media_files USING gin(combined_analysis)');

            // B-tree indexes for scalar columns
            DB::statement('CREATE INDEX media_files_analysis_mode_idx ON media_files(analysis_mode)');
            DB::statement('CREATE INDEX media_files_analysis_completed_at_idx ON media_files(analysis_completed_at)');

            // Expression index for quick filtering by people count (text-based, sufficient for filtering)
            DB::statement("CREATE INDEX media_files_people_count_idx ON media_files((people_analysis->>'people_count')) WHERE people_analysis IS NOT NULL");

            // Expression index for filtering by has_people
            DB::statement("CREATE INDEX media_files_has_people_idx ON media_files((combined_analysis->>'has_people')) WHERE combined_analysis IS NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes first (PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS media_files_content_analysis_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_people_analysis_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_quality_analysis_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_context_analysis_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_combined_analysis_gin');
            DB::statement('DROP INDEX IF EXISTS media_files_analysis_mode_idx');
            DB::statement('DROP INDEX IF EXISTS media_files_analysis_completed_at_idx');
            DB::statement('DROP INDEX IF EXISTS media_files_people_count_idx');
            DB::statement('DROP INDEX IF EXISTS media_files_has_people_idx');
        }

        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn([
                'content_analysis',
                'people_analysis',
                'quality_analysis',
                'context_analysis',
                'combined_analysis',
                'analysis_mode',
                'analysis_passes_completed',
                'analysis_passes_failed',
                'analysis_duration_seconds',
                'analysis_completed_at',
            ]);
        });
    }
};
