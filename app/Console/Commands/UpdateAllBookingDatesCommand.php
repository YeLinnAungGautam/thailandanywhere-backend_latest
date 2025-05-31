<?php

namespace App\Console\Commands;

use App\Jobs\UpdateBookingDatesJob;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAllBookingDatesCommand extends Command
{
    protected $signature = 'bookings:update-dates';
    protected $description = 'Update start/end dates for all bookings via queued jobs';

    public function handle()
    {
        $this->info('Starting batch update of all bookings...');

        $query = Booking::select('id');
        $total = $query->count();

        if ($total === 0) {
            return $this->info('No bookings found.');
        }

        $bar = $this->output->createProgressBar($total);
        $this->info("Dispatching jobs for {$total} bookings...");

        $query->chunk(1000, function ($bookings) use ($bar) {
            foreach ($bookings as $booking) {
                try {
                    UpdateBookingDatesJob::dispatch($booking->id);
                } catch (\Exception $e) {
                    Log::error("Booking {$booking->id} failed: " . $e->getMessage());
                    $this->error("Failed: Booking {$booking->id}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('All booking update jobs dispatched successfully!');
    }
}
