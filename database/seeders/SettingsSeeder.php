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
                'value' => 'Salesforce/blip-image-captioning-large',
                'description' => 'Model used for generating image captions',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'embedding_model',
                'value' => 'laion/CLIP-ViT-B-32-laion2B-s34B-b79K',
                'description' => 'Model used for generating image embeddings',
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
                'value' => 'false',
                'description' => 'Enable Ollama for detailed descriptions',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ollama_model',
                'value' => 'llava',
                'description' => 'Ollama model to use for detailed descriptions',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
        
        $this->command->info('Default settings seeded successfully!');
    }
}

