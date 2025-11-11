<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AutoTrainAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:auto-train 
                            {--force : Force training even if not enough new images}
                            {--min-images=100 : Minimum new images before training}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically export training data and trigger AI training';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🤖 Auto-Train AI System Starting...');
        
        // Check if we have enough images to make training worthwhile (use PostgreSQL)
        $totalImages = DB::connection('pgsql')
            ->table('image_files')
            ->whereNotNull('embedding')
            ->where('processing_status', 'completed')
            ->count();
            
        if ($totalImages < 10) {
            $this->warn("⚠️  Only {$totalImages} images available. Need at least 10 for training.");
            return Command::SUCCESS;
        }
        
        // Check if we have new images since last training
        $lastTrainingFile = storage_path('app/training/last_training.txt');
        $minImages = $this->option('min-images');
        
        if (!$this->option('force') && file_exists($lastTrainingFile)) {
            $lastTrainingTime = (int) file_get_contents($lastTrainingFile);
            $newImagesCount = DB::connection('pgsql')
                ->table('image_files')
                ->where('created_at', '>', date('Y-m-d H:i:s', $lastTrainingTime))
                ->whereNotNull('embedding')
                ->where('processing_status', 'completed')
                ->count();
                
            if ($newImagesCount < $minImages) {
                $this->info("ℹ️  Only {$newImagesCount} new images since last training. Need {$minImages}.");
                return Command::SUCCESS;
            }
            
            $this->info("📊 Found {$newImagesCount} new images since last training");
        }
        
        // Step 1: Export training data
        $this->info('📤 Exporting training data...');
        $this->call('export:training-data', ['--limit' => 5000]);
        
        // Step 2: Trigger Python AI training
        $this->info('🧠 Triggering AI training...');
        
        try {
            $response = Http::timeout(10)->post(config('ai.api_url') . '/train', [
                'training_data_path' => '/app/training_data/training_data.json'
            ]);
            
            if ($response->successful()) {
                $this->info('✅ AI training triggered successfully!');
                
                // Record training time
                file_put_contents($lastTrainingFile, time());
                
                Log::info('Auto-training completed successfully', [
                    'total_images' => $totalImages,
                    'timestamp' => now()
                ]);
            } else {
                $this->error('❌ AI training failed: ' . $response->body());
                Log::error('Auto-training failed', ['response' => $response->body()]);
            }
        } catch (\Exception $e) {
            $this->error('❌ Error triggering training: ' . $e->getMessage());
            Log::error('Auto-training error', ['error' => $e->getMessage()]);
        }
        
        return Command::SUCCESS;
    }
}

