<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MonitorSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:monitor 
                            {--fix : Attempt to fix detected issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor system health and attempt auto-recovery';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏥 System Health Monitor Starting...');
        
        $issues = [];
        
        // Check Python AI Service
        try {
            $response = Http::timeout(5)->get(config('ai.api_url') . '/health');
            if ($response->successful()) {
                $this->info('✅ Python AI Service: Healthy');
            } else {
                $issues[] = 'Python AI Service: Unhealthy';
                $this->error('❌ Python AI Service: Unhealthy');
            }
        } catch (\Exception $e) {
            $issues[] = 'Python AI Service: Unreachable';
            $this->error('❌ Python AI Service: Unreachable - ' . $e->getMessage());
        }
        
        // Check Ollama Service
        try {
            $response = Http::timeout(5)->get(config('ollama.url', 'http://ollama:11434') . '/api/tags');
            if ($response->successful()) {
                $models = $response->json()['models'] ?? [];
                $this->info('✅ Ollama Service: Healthy (' . count($models) . ' models)');
                
                // Check if required model is present
                $requiredModel = config('ollama.model', 'llava');
                $hasModel = collect($models)->contains(function ($model) use ($requiredModel) {
                    return str_contains($model['name'], $requiredModel);
                });
                
                if (!$hasModel) {
                    $issues[] = "Ollama: Missing required model '{$requiredModel}'";
                    $this->warn("⚠️  Ollama: Missing required model '{$requiredModel}'");
                }
            } else {
                $issues[] = 'Ollama Service: Unhealthy';
                $this->error('❌ Ollama Service: Unhealthy');
            }
        } catch (\Exception $e) {
            $issues[] = 'Ollama Service: Unreachable';
            $this->warn('⚠️  Ollama Service: Unreachable (optional service)');
        }
        
        // Check Database (use PostgreSQL)
        try {
            DB::connection('pgsql')->getPdo();
            $this->info('✅ Database: Connected');
        } catch (\Exception $e) {
            $issues[] = 'Database: Connection failed';
            $this->error('❌ Database: Connection failed');
        }
        
        // Check Queue (use PostgreSQL)
        $pendingJobs = DB::connection('pgsql')->table('jobs')->count();
        $failedJobs = DB::connection('pgsql')->table('failed_jobs')->count();
        
        $this->info("📊 Queue: {$pendingJobs} pending, {$failedJobs} failed");
        
        if ($pendingJobs > 100) {
            $issues[] = "Queue: High backlog ({$pendingJobs} jobs)";
            $this->warn("⚠️  Queue: High backlog ({$pendingJobs} jobs)");
        }
        
        if ($failedJobs > 10) {
            $issues[] = "Queue: Many failed jobs ({$failedJobs})";
            $this->warn("⚠️  Queue: Many failed jobs ({$failedJobs})");
        }
        
        // Check Processing Status (use PostgreSQL)
        $processingImages = DB::connection('pgsql')
            ->table('media_files')
            ->where('processing_status', 'processing')
            ->where('processing_started_at', '<', now()->subMinutes(10))
            ->count();

        if ($processingImages > 0) {
            $issues[] = "Media: {$processingImages} stuck in processing";
            $this->warn("⚠️  Media: {$processingImages} stuck in processing");

            if ($this->option('fix')) {
                // Reset stuck media files (use PostgreSQL)
                DB::connection('pgsql')
                    ->table('media_files')
                    ->where('processing_status', 'processing')
                    ->where('processing_started_at', '<', now()->subMinutes(10))
                    ->update([
                        'processing_status' => 'pending',
                        'processing_started_at' => null,
                        'processing_error' => 'Reset by monitor: Stuck in processing'
                    ]);

                $this->info("🔧 Reset {$processingImages} stuck media files to pending");
            }
        }
        
        // Log results
        if (empty($issues)) {
            $this->info('🎉 System Health: All Clear!');
            Log::info('System health check: All services healthy');
        } else {
            $this->error('⚠️  System Health: Issues Detected');
            Log::warning('System health check: Issues detected', ['issues' => $issues]);
        }
        
        return empty($issues) ? Command::SUCCESS : Command::FAILURE;
    }
}

