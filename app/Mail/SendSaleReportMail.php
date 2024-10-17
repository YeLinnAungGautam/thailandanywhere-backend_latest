<?php

namespace App\Mail;

use App\Exports\ReservationReportExport;
use Excel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendSaleReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $daterange;
    public $type;

    /**
     * Create a new message instance.
     */
    public function __construct($daterange, $type)
    {
        $this->daterange = $daterange;
        $this->type = $type;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Send Sale Report Mail'
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email.daily_sale_report_email',
            with: [
                'type' => $this->type
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
        $file_name = "reservation_report_" . date('Y-m-d-H-i-s') . ".csv";

        Excel::store(new ReservationReportExport($this->daterange), "attachments/" . $file_name);

        return [
            Attachment::fromPath(public_path('/storage/attachments/' . $file_name))
        ];
    }
}
