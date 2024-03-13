<?php

namespace App\Jobs;

use App\Mail\HotelConfirmationReceiptUploadNotifierEmail;
use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class HotelConfirmationReceiptUploadNotifierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected array $paid_slip_names, protected BookingItem $booking_item)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $agent_email = $this->booking_item->booking->createdBy->email ?? '';

        $mail_to = ['ceo@thanywhere.com', $agent_email];

        Mail::to($mail_to)->send(new HotelConfirmationReceiptUploadNotifierEmail($this->paid_slip_names));
    }
}
