<?php

namespace App\Jobs;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateBookingDatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $bookingId;

    public function __construct($bookingId)
    {
        $this->bookingId = $bookingId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $booking = Booking::find($this->bookingId);

        if (!$booking) {
            return;
        }

        // Get all booking items with service dates
        $bookingItems = $booking->items()
            ->whereNotNull('service_date')
            ->get();

        if ($bookingItems->isEmpty()) {
            return;
        }

        $serviceDates = [];
        $hotelCheckinDates = [];
        $hotelCheckoutDates = [];

        foreach ($bookingItems as $item) {
            if ($item->product_type === 'App\\Models\\Hotel') {
                if ($item->checkin_date) {
                    $hotelCheckinDates[] = Carbon::parse($item->checkin_date)->format('Y-m-d');
                }
                if ($item->checkout_date) {
                    $hotelCheckoutDates[] = Carbon::parse($item->checkout_date)->format('Y-m-d');
                }
            }

            // Add service date (formatted as Y-m-d)
            $serviceDates[] = Carbon::parse($item->service_date)->format('Y-m-d');
        }

        // Combine all dates for comparison
        $allStartDates = array_merge($serviceDates, $hotelCheckinDates);
        $allEndDates = array_merge($serviceDates, $hotelCheckoutDates);

        // Find the earliest (min) and latest (max) dates
        $startDate = !empty($allStartDates) ? min($allStartDates) : null;
        $endDate = !empty($allEndDates) ? max($allEndDates) : null;

        // Update booking with formatted dates
        if ($startDate && $endDate) {
            $booking->update([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        }
    }
}
