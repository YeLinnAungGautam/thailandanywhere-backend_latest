<?php

// Create this file: app/Jobs/UpdateBalanceDueDateJob.php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpdateBalanceDueDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bookingId;

    public function __construct(int $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function handle(): void
    {
        try {
            $booking = Booking::whereHas('items', fn($q) => $q->whereNotNull('service_date'))
                ->with(['items' => fn($q) => $q->whereNotNull('service_date')->orderBy('service_date')])
                ->find($this->bookingId);

            if (!$booking) {
                Log::warning("Booking not found", ['booking_id' => $this->bookingId]);
                return;
            }

            // Ensure we have a Carbon instance
            $earliestDate = $booking->items->first()?->service_date;
            $earliestDate = $earliestDate ? Carbon::parse($earliestDate) : null;

            if (!$earliestDate) {
                Log::info("No valid service dates found", ['booking_id' => $this->bookingId]);
                return;
            }

            // Format dates for comparison
            $currentDue = $booking->balance_due_date ? Carbon::parse($booking->balance_due_date)->format('Y-m-d') : null;
            $newDue = $earliestDate->format('Y-m-d');

            if ($currentDue !== $newDue) {
                $booking->update(['balance_due_date' => $earliestDate->format('Y-m-d')]);

                Log::info("Updated balance_due_date", [
                    'booking_id' => $this->bookingId,
                    'old_date' => $currentDue,
                    'new_date' => $newDue
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Job failed", [
                'booking_id' => $this->bookingId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed permanently", [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage()
        ]);
    }
}
