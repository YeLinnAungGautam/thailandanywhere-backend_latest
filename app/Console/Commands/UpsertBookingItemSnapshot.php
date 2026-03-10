<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BookingItem;
use App\Jobs\UpsertBookingItemSnapshotJob;

class UpsertBookingItemSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upsert:booking-item-snapshot {--start-date=2026-01-01}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upsert booking item snapshots from a specific start date to current time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->option('start-date');

        $this->info("Fetching booking items from {$startDate} to now...");

        $query = BookingItem::whereDate('created_at', '>=', $startDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No booking items found.");
            return;
        }

        $this->info("Found {$count} booking items. Dispatching jobs...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(500, function ($bookingItems) use ($bar) {
            foreach ($bookingItems as $item) {
                UpsertBookingItemSnapshotJob::dispatch($item);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('All jobs dispatched successfully.');
    }
}
