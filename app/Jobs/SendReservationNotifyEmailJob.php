<?php

namespace App\Jobs;

use App\Mail\ReservationNotifyEmail;
use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReservationNotifyEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $default_email = 'negyi.partnership@thanywhere.com';
    protected $mail_to;
    protected $mail_subject;
    protected $sent_to_default;
    protected $booking_item;

    /**
     * Create a new job instance.
     */
    public function __construct($mail_to, $mail_subject, $sent_to_default, BookingItem $booking_item)
    {
        $this->mail_to = $mail_to;
        $this->mail_subject = $mail_subject;
        $this->sent_to_default = $sent_to_default;
        $this->booking_item = $booking_item;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->getMails())->send(new ReservationNotifyEmail($this->mail_subject, $this->booking_item));
    }

    public function getMails()
    {
        if(isset($this->mail_to) && $this->sent_to_default) {
            $mails = [$this->mail_to, $this->default_email];
        } elseif(isset($this->mail_to) && $this->sent_to_default == false) {
            $mails = [$this->mail_to];
        } else {
            $mails = [$this->default_email];
        }

        return $mails;
    }
}
