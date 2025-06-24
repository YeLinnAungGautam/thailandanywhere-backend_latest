<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItemGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class migrateBookingItemGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public $bookings)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->bookings as $booking) {
            $grouped = $booking->items->groupBy(function ($item) {
                return $item->product_type . ':' . $item->product_id;
            });

            foreach ($grouped as $key => $items) {
                [$product_type, $product_id] = explode(':', $key);

                $total_cost_price = $items->sum('cost_price');

                $group = BookingItemGroup::updateOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'product_type' => $product_type,
                        'product_id' => $product_id,
                    ],
                    [
                        'total_cost_price' => $total_cost_price,
                    ]
                );

                foreach ($items as $item) {
                    $item->update(['group_id' => $group->id]);
                }
            }
        }
    }
}
