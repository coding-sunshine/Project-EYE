<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessImageAnalysis;

class AutoReanalyzeImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:auto-reanalyze 
                            {--batch=50 : Number of images to reanalyze per run}
                            {--priority=oldest : Priority: oldest, random, failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically reanalyze images to improve quality with updated AI models';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Auto-Reanalyze System Starting...');
        
        $batch = (int) $this->option('batch');
        $priority = $this->option('priority');
        
        // Build query based on priority (use PostgreSQL connection)
        $query = DB::connection('pgsql')
            ->table('image_files')
            ->whereNull('deleted_at');
            
        switch ($priority) {
            case 'failed':
                // Reanalyze failed images first
                $query->where('processing_status', 'failed');
                $this->info('🎯 Priority: Failed images');
                break;
                
            case 'random':
                // Random selection for diversity
                $query->whereIn('processing_status', ['completed', 'pending'])
                    ->inRandomOrder();
                $this->info('🎲 Priority: Random selection');
                break;
                
            case 'oldest':
            default:
                // Oldest images (more likely to benefit from improved models)
                $query->whereIn('processing_status', ['completed', 'pending'])
                    ->orderBy('processing_completed_at', 'asc')
                    ->orderBy('created_at', 'asc');
                $this->info('📅 Priority: Oldest images');
                break;
        }
        
        $images = $query->limit($batch)->get(['id', 'original_filename', 'processing_status']);
        
        if ($images->isEmpty()) {
            $this->info('ℹ️  No images found for reanalysis');
            return Command::SUCCESS;
        }
        
        $this->info("📊 Found {$images->count()} images to reanalyze");
        
        $dispatched = 0;
        $failed = 0;
        
        foreach ($images as $image) {
            try {
                // Dispatch reanalysis job
                ProcessImageAnalysis::dispatch($image->id)
                    ->onQueue('image-processing');
                    
                $dispatched++;
                
                Log::info('Auto-reanalysis dispatched', [
                    'image_id' => $image->id,
                    'filename' => $image->original_filename,
                    'priority' => $priority
                ]);
                
            } catch (\Exception $e) {
                $failed++;
                Log::error('Auto-reanalysis dispatch failed', [
                    'image_id' => $image->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("✅ Dispatched {$dispatched} reanalysis jobs");
        
        if ($failed > 0) {
            $this->warn("⚠️  Failed to dispatch {$failed} jobs");
        }
        
        Log::info('Auto-reanalysis batch completed', [
            'dispatched' => $dispatched,
            'failed' => $failed,
            'priority' => $priority,
            'timestamp' => now()
        ]);
        
        return Command::SUCCESS;
    }
}

