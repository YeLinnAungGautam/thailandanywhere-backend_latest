<?php

namespace App\Mail;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ReservationNotifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $mail_subject;
    protected $mail_body;
    protected $booking_item;
    public $attach_files;

    /**
     * Create a new message instance.
     */
    public function __construct(string $mail_subject, string $mail_body, BookingItem $booking_item, $attach_files = null)
    {
        $this->mail_subject = $mail_subject;
        $this->mail_body = $mail_body;
        $this->booking_item = $booking_item;
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
    public function attachments(): array
    {
        $passports = $this->booking_item->reservationCustomerPassport;
        $expense_receipts = $this->booking_item->reservationReceiptImage;

        $files = [];
        if ($passports->count()) {
            foreach ($passports as $passport) {
                if (Storage::exists('/passport/' . $passport->file)) {
                    $files[] = Attachment::fromPath(public_path('/storage/passport/' . $passport->file));
                }
            }
        }

        if ($expense_receipts->count()) {
            foreach ($expense_receipts as $paid_slip) {
                if (Storage::exists('/images/' . $paid_slip->file)) {
                    $files[] = Attachment::fromPath(public_path('/storage/images/' . $paid_slip->file));
                }
            }
        }

        if (isset($this->attach_files) && count($this->attach_files) > 0) {
            foreach ($this->attach_files as $attach_file) {
                $files[] = Attachment::fromPath(public_path('/storage/temp_files/attachments/' . $attach_file));
            }
        }

        return $files;
    }
}
