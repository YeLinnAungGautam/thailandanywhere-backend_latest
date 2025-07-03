<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:delete-unverified';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete unverified users who registered more than 30 minutes ago';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cutoffTime = Carbon::now()->subMinutes(30);

        // Find unverified users created more than 30 minutes ago
        $users = User::query()
            ->where('is_active', false)
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoffTime)
            ->get();

        $count = $users->count();

        if ($count > 0) {
            foreach ($users as $user) {
                $user->delete();
            }

            $this->info("{$count} unverified users have been deleted.");
        } else {
            $this->info("No unverified users to delete.");
        }

        return Command::SUCCESS;
    }
}
