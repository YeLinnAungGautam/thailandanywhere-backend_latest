<?php

namespace App\Mail;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
        $paid_slips = $this->booking_item->reservationPaidSlip;

        $files = [];
        if($passports->count()) {
            foreach($passports as $passport) {
                $path = public_path('/storage/passport/' . $passport->file);
                $files[] = Attachment::fromPath($path);
            }
        }

        if($paid_slips->count()) {
            foreach($paid_slips as $paid_slip) {
                $path = public_path('/storage/images/' . $paid_slip->file);
                $files[] = Attachment::fromPath($path);
            }
        }

        if(isset($this->attach_files) && count($this->attach_files) > 0) {
            foreach($this->attach_files as $attach_file) {
                $path = public_path('/storage/temp_files/attachments/' . $attach_file);
                $files[] = Attachment::fromPath($path);
            }
        }

        return $files;
    }
}
