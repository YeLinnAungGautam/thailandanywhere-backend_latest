<?php

namespace App\Console\Commands;

use App\Jobs\UpdateStatusJob;
use App\Models\Booking;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateStatusCommand extends Command
{
    protected $signature = 'status:update';
    protected $description = 'Update app_show_status for all orders and bookings';

    /**
     * Execute the console command.
     */
    public function handle () {
        UpdateStatusJob::dispatch();
    }
}
