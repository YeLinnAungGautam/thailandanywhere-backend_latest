<?php

namespace App\Console\Commands;

use App\Jobs\ReservationGroup\upsertBookingItemGroup;
use Illuminate\Console\Command;

class MigrateBookingItemGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:booking-item-group';

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
        upsertBookingItemGroup::dispatch();
    }
}
