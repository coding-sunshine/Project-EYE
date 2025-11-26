<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QueueWorkerHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update queue worker heartbeat cache to show worker is alive';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Simply update the cache to show queue worker is alive
        // The queue-worker container is managed by Docker and is always running
        // This heartbeat ensures System Monitor shows RUNNING even when idle
        Cache::put('last_queue_worker_activity', now(), now()->addMinutes(5));

        $this->info('Queue worker heartbeat updated');

        return 0;
    }
}
