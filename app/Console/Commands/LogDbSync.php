<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LogDbSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:log-sync {status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log DB sync status into Laravel logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $status = $this->argument('status');

        if ($status === 'success') {
            Log::info('✅ Database sync completed successfully at ' . now());
        } else {
            Log::error('❌ Database sync failed at ' . now());
        }

        $this->info("DB sync log recorded: {$status}");

        return 0;
    }
}
