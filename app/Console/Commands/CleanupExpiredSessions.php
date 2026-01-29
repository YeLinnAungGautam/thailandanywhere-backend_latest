<?php

namespace App\Console\Commands;

use App\Models\UserSession;
use Illuminate\Console\Command;

class CleanupExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired tracking sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = UserSession::where('expires_at', '<', now()->subDays(7))
            ->delete();

        $this->info("✅ Deleted {$deleted} expired sessions");

        return 0;
    }
}
