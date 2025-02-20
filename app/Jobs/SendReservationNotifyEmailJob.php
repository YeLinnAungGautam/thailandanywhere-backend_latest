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
    protected $mail_body;
    protected $booking_item;
    protected $ccEmail;
    public $attachments;

    /**
     * Create a new job instance.
     */
    public function __construct($mail_to, $mail_subject, $sent_to_default, $mail_body, BookingItem $booking_item, $attachments = null, $ccEmail = null)
    {
        $this->mail_to = $mail_to;
        $this->mail_subject = $mail_subject;
        $this->sent_to_default = $sent_to_default;
        $this->mail_body = $mail_body;
        $this->booking_item = $booking_item;
        $this->attachments = $attachments;
        $this->ccEmail = $ccEmail;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->getMails())->cc($this->ccEmail)
            ->send(new ReservationNotifyEmail($this->mail_subject, $this->mail_body, $this->booking_item, $this->attachments));
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
