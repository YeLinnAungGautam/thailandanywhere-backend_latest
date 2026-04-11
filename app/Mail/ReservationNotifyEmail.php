<?php

namespace App\Mail;

use App\Traits\UsesHotelServiceMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ReservationNotifyEmail extends Mailable
{
    use Queueable, SerializesModels, UsesHotelServiceMail;

    protected $mail_subject;
    protected $mail_body;
    protected $booking_item_group;
    public $attach_files;

    /**
     * Create a new message instance.
     */
    public function __construct(string $mail_subject, string $mail_body, $booking_item_group, $attach_files = null)
    {
        $this->mail_subject = $mail_subject;
        $this->mail_body = $mail_body;
        $this->booking_item_group = $booking_item_group;
        $this->attach_files = $attach_files;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mail_subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email.reservation_notify_email',
            with: [
                'mail_body' => $this->mail_body
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    // public function attachments(): array
    // {
    //     $files = [];

    //     // Get passports from the group
    //     foreach ($this->booking_item_group->passports as $passport) {
    //         if (Storage::exists('passport/' . $passport->file)) {
    //             $files[] = Attachment::fromPath(storage_path('app/public/passport/' . $passport->file));
    //         }
    //     }

    //     // Get other documents (receipts etc) if needed via the group
    //     // ... attachment logic ...
    //     if (isset($this->attach_files) && count($this->attach_files) > 0) {
    //         foreach ($this->attach_files as $attach_file) {
    //             $files[] = Attachment::fromPath(storage_path('app/public/temp_files/attachments/' . $attach_file));
    //         }
    //     }

    //     return $files;
    // }
    public function attachments(): array
    {
        $files = [];

        foreach ($this->booking_item_group->passports as $passport) {
            // ✅ null/empty check
            if (!$passport->file) {
                continue;
            }

            // ✅ public/ prefix ထည့်မှ storage/app/public/passport/ ကို မှန်ကန်စွာ ရှာသည်
            if (Storage::exists('public/passport/' . $passport->file)) {
                $files[] = Attachment::fromPath(storage_path('app/public/passport/' . $passport->file));
            }
        }

        if (isset($this->attach_files) && count($this->attach_files) > 0) {
            foreach ($this->attach_files as $attach_file) {
                // ✅ temp file လည်း exist check ထည့်သည်
                if (Storage::exists('public/temp_files/attachments/' . $attach_file)) {
                    $files[] = Attachment::fromPath(storage_path('app/public/temp_files/attachments/' . $attach_file));
                }
            }
        }

        return $files;
    }
}

