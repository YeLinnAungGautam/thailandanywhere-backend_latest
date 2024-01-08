<?php
namespace App\Services;

use App\Mail\ReservationNotifyEmail;
use App\Models\BookingItem;
use Illuminate\Support\Facades\Mail;

class ReservationEmailNotifyService
{
    protected $default_email = 'negyi.partnership@thanywhere.com';
    protected $mail_to;
    protected $mail_subject;
    protected $sent_to_default;
    protected $mail_body;
    protected $booking_item;
    public $attachments;

    public function __construct($mail_to, $mail_subject, $sent_to_default, $mail_body, BookingItem $booking_item, $attachments = null)
    {
        $this->mail_to = $mail_to;
        $this->mail_subject = $mail_subject;
        $this->sent_to_default = $sent_to_default;
        $this->mail_body = $mail_body;
        $this->booking_item = $booking_item;
        $this->attachments = $attachments;
    }

    public function send()
    {
        Mail::to($this->getMails())
            ->send(new ReservationNotifyEmail($this->mail_subject, $this->mail_body, $this->booking_item, $this->saveAttachToTemp()));
    }

    private function saveAttachToTemp()
    {
        $attach_files = [];

        if(isset($this->attachments)) {
            foreach ($this->attachments as $attachment) {
                $attach_files[] = uploadFile($attachment, '/temp_files/attachments/');
            }
        }

        return $attach_files;
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
