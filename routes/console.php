<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks - Self-Sustaining 24/7 System
|--------------------------------------------------------------------------
|
| These tasks make Avinash-EYE a fully automated, self-sustaining system
| that runs 24/7 with automatic training, reanalysis, and monitoring.
|
*/

// System Health Monitoring (every 5 minutes)
// Monitors all services and auto-fixes issues
Schedule::command('system:monitor --fix')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('System health check completed successfully');
    })
    ->onFailure(function () {
        \Log::error('System health check detected issues');
    });

// Export Training Data (daily at 1 AM)
// Backs up training data for AI improvement
Schedule::command('export:training-data --limit=5000')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Training data exported successfully');
    });

// Auto-Train AI (daily at 2 AM)
// Trains AI on uploaded images to improve quality
Schedule::command('ai:auto-train --min-images=50')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('AI auto-training completed successfully');
    })
    ->onFailure(function () {
        \Log::error('AI auto-training failed');
    });

// Auto-Reanalyze Images (every 6 hours)
// Gradually improves all images with latest AI models
Schedule::command('ai:auto-reanalyze --batch=25 --priority=oldest')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Auto-reanalysis batch completed');
    });

// Prune Old Failed Jobs (weekly cleanup on Sundays at 3 AM)
// Keeps the database clean
Schedule::command('queue:prune-failed --hours=168')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->onSuccess(function () {
        \Log::info('Old failed jobs pruned');
    });
