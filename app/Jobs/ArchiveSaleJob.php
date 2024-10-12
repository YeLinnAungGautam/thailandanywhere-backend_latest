<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ArchiveSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Booking $booking, public array $input_data = [])
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customer = $this->booking->customer;
        $user = $this->booking->user;

        $booking_archive_data = [
            'customer' => $customer,
            'user' => $user,
            'input_data' => $this->input_data
        ];

        $this->booking->update(['archive' => json_encode($booking_archive_data)]);

        foreach ($this->booking->items as $booking_item) {
            $product = $booking_item->product;
            $variation = $booking_item->acsr_variation_product;

            $booking_item_archive_data = [
                'product' => $product,
                'variation' => $variation,
            ];

            $booking_item->update(['archive' => json_encode($booking_item_archive_data)]);
        }
    }
}
