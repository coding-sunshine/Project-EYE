<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if settings already exist
        $existingSettings = DB::table('settings')->count();
        
        if ($existingSettings > 0) {
            $this->command->info('Settings already exist. Skipping seed.');
            return;
        }

        // Insert default settings
        $settings = [
            [
                'key' => 'captioning_model',
                'value' => 'florence',
                'description' => 'Model used for generating image captions (florence or blip)',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'embedding_model',
                'value' => 'clip',
                'description' => 'Model used for generating image embeddings (clip=512dims, siglip=768dims, aimv2=1024dims)',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'face_detection_enabled',
                'value' => 'true',
                'description' => 'Enable face detection in images',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ollama_enabled',
                'value' => 'true',
                'description' => 'Enable Ollama for detailed descriptions and scene classification',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ollama_model',
                'value' => 'llava:latest',
                'description' => 'Ollama model to use for image/video analysis (vision model)',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ollama_model_document',
                'value' => 'qwen2.5:7b',
                'description' => 'Ollama model to use for document analysis and summaries',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ocr_engine',
                'value' => 'auto',
                'description' => 'OCR engine to use (auto, paddleocr, tesseract)',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
        
        $this->command->info('Default settings seeded successfully!');
    }
}

