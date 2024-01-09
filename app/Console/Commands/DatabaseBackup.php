<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automating Daily Backups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! Storage::exists('db-backup')) {
            Storage::makeDirectory('db-backup');
        }

        $filename = "db-backup-" . Carbon::now()->format('Y-m-d') . ".sql";

        $command = "mysqldump --user=" . env('DB_USERNAME') ." --password=" . env('DB_PASSWORD')
                . " --host=" . env('DB_HOST') . " " . env('DB_DATABASE')
                . "  | gzip > " . storage_path() . "/app/db-backup/" . $filename;

        $returnVar = null;
        $output = null;

        exec($command, $output, $returnVar);
    }
}
