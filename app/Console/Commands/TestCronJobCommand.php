<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCronJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cron-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info('cron is running:' . now()->format('Y-m-d H:i:s'));
    }
}
