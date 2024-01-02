<?php

namespace App\Mail;

use App\Models\BookingItem;
use App\Models\Hotel;
use App\Services\BookingItemDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationNotifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $mail_subject;
    protected $booking_item;

    /**
     * Create a new message instance.
     */
    public function __construct(string $mail_subject, BookingItem $booking_item)
    {
        $this->mail_subject = $mail_subject;
        $this->booking_item = $booking_item;
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
        $service = new BookingItemDataService($this->booking_item);

        return new Content(
            view: 'email.reservation_notify_email',
            with: [
                'booking_item' => $this->booking_item,
                'total_nights' => $this->booking_item->product_type == Hotel::class ? $service->getNights($this->booking_item->checkin_date, $this->booking_item->checkout_date) : 0,
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
    }
}
