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
    public $attachments;

    /**
     * Create a new message instance.
     */
    public function __construct(string $mail_subject, string $mail_body, BookingItem $booking_item, $attachments = null)
    {
        $this->mail_subject = $mail_subject;
        $this->mail_body = $mail_body;
        $this->booking_item = $booking_item;
        $this->attachments = $attachments;
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
        return [];

        // $passports = $this->booking_item->reservationCustomerPassport;
        // $paid_slips = $this->booking_item->reservationPaidSlip;

        // $attachment_lists = [];

        // if(isset($this->attachments)) {
        //     foreach($this->attachments as $attachment) {
        //         $attachment_lists[] = Attachment::fromData(fn () => $attachment, $attachment->getClientOriginalName())->withMime($attachment->getMimeType());
        //     }
        // }

        // // dd($attachment_lists);

        // return $attachment_lists;

        // return [
        //     // Attachment::fromData(fn () => $this->pdf, 'Report.pdf'),
        // ];
    }
}
