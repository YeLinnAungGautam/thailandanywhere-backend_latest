<?php

namespace App\Console\Commands;

use App\Models\BookingItemGroup;
use Illuminate\Console\Command;

class FilterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:filter-mail';

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
        $this->info('Starting to filter booking item groups...');

        // Find booking item groups where:
        // 1. sent_booking_request = 1
        // 2. Does NOT have customerDocuments with type 'booking_request_proof'
        $affectedGroups = BookingItemGroup::where('sent_booking_request', 1)
            ->whereDoesntHave('customerDocuments', function ($query) {
                $query->where('type', 'booking_request_proof');
            })
            ->update(['sent_booking_request' => 0]);


        $this->info("Successfully updated {$affectedGroups} booking item group(s).");
        $this->info('Filtering completed!');

        return Command::SUCCESS;
    }
}
