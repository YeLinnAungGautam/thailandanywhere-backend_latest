<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SyncUatDatabase extends Command
{
    protected $signature = 'db:sync-uat {--compress}';
    protected $description = 'Sync production database to UAT database';

    public function handle(): int
    {
        // Get DB configs from env (so you don’t hardcode passwords)
        $prodHost = config('database.connections.mysql_prod.host');
        $prodUser = config('database.connections.mysql_prod.username');
        $prodPass = config('database.connections.mysql_prod.password');
        $prodDb = config('database.connections.mysql_prod.database');

        $uatHost = config('database.connections.mysql_uat.host');
        $uatUser = config('database.connections.mysql_uat.username');
        $uatPass = config('database.connections.mysql_uat.password');
        $uatDb = config('database.connections.mysql_uat.database');

        $compress = $this->option('compress');

        // Build the dump + import command
        $dumpCmd = "mysqldump -h {$prodHost} -u {$prodUser} -p'{$prodPass}' {$prodDb}";
        $pipeCmd = "mysql -h {$uatHost} -u {$uatUser} -p'{$uatPass}' {$uatDb}";

        if ($compress) {
            $command = "{$dumpCmd} | gzip | gunzip | {$pipeCmd}";
        } else {
            $command = "{$dumpCmd} | {$pipeCmd}";
        }

        info("Starting DB sync: {$prodDb} → {$uatDb} ...");
        $this->info("Starting DB sync: {$prodDb} → {$uatDb} ...");

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(null); // allow large db
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if ($process->isSuccessful()) {
            info('Database sync completed successfully.');
            $this->info('Database sync completed successfully.');

            return self::SUCCESS;
        }

        Log::error('Database sync failed.');
        $this->error('Database sync failed.');

        return self::FAILURE;
    }
}
