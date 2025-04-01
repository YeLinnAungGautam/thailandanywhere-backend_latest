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
    protected $email_type;
    public $attachments;

    /**
     * Create a new job instance.
     */
    public function __construct($mail_to, $mail_subject, $sent_to_default, $mail_body, BookingItem $booking_item, $attachments = null, $ccEmail = null, $email_type = 'booking')
    {
        $this->mail_to = $mail_to;
        $this->mail_subject = $mail_subject;
        $this->sent_to_default = $sent_to_default;
        $this->mail_body = $mail_body;
        $this->booking_item = $booking_item;
        $this->attachments = $attachments;
        $this->ccEmail = $ccEmail;
        $this->email_type = $email_type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->getMails())->cc($this->ccEmail)
            ->send(new ReservationNotifyEmail($this->mail_subject, $this->mail_body, $this->booking_item, $this->attachments));

        if($this->email_type == 'booking'){
            $this->booking_item->update(['is_booking_request' => true]);
        }else if($this->email_type == 'expense'){
            $this->booking_item->update(['is_expense_email_sent' => true]);
        }
    }

    // public function getMails()
    // {
    //     if (isset($this->mail_to) && $this->sent_to_default) {
    //         $mails = [$this->mail_to, $this->default_email];
    //     } elseif (isset($this->mail_to) && $this->sent_to_default == false) {
    //         $mails = [$this->mail_to];
    //     } else {
    //         $mails = [$this->default_email];
    //     }

    //     return $mails;
    // }

    public function getMails()
    {
        $mails = [];

        if (isset($this->mail_to)) {
            // Handle if mail_to is already an array
            if (is_array($this->mail_to)) {
                $mails = $this->mail_to;
            } else {
                $mails = [$this->mail_to];
            }

            // Add default email if sent_to_default is true
            if ($this->sent_to_default && !in_array($this->default_email, $mails)) {
                $mails[] = $this->default_email;
            }
        } else {
            $mails = [$this->default_email];
        }

        return $mails;
    }
}
