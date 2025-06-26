<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateBookingBalanceDueDates extends Command
{
    protected $signature = 'bookings:update-due-dates';
    protected $description = 'Update balance_due_date to match earliest service_date from booking items';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '🔍 [DRY RUN] Checking bookings...' : '🔄 Updating bookings...');

        // Get bookings with items that have service dates
        $bookings = Booking::whereHas('items', fn($q) => $q->whereNotNull('service_date'))
            ->with(['items' => fn($q) => $q->whereNotNull('service_date')->orderBy('service_date')])
            ->get();

        if ($bookings->isEmpty()) {
            $this->warn('No bookings with service dates found!');
            return;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($bookings as $booking) {
            $earliestDate = $booking->items->first()->service_date ?? null;

            if (!$earliestDate) {
                $skipped++;
                continue;
            }

            // Format dates for consistent comparison (YYYY-MM-DD)
            $currentDue = $booking->balance_due_date?->format('Y-m-d');
            $newDue = $earliestDate->format('Y-m-d');

            if ($currentDue !== $newDue) {
                if (!$dryRun) {
                    $booking->update(['balance_due_date' => $earliestDate]);
                }
                $updated++;
                $this->line(sprintf(
                    'Booking %d: %s → %s',
                    $booking->id,
                    $currentDue ?? 'NULL',
                    $newDue
                ));
            } else {
                $skipped++;
            }
        }

        $this->info(sprintf(
            '%sUpdated: %d, Skipped: %d',
            $dryRun ? '[DRY RUN] ' : '',
            $updated,
            $skipped
        ));

        return 0;
    }
}
