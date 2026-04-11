<?php

namespace App\Jobs;

use App\Mail\ReservationNotifyEmail;
use App\Models\BookingItemGroup;
use App\Models\CustomerDocument;
use App\Models\EmailLog;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReservationNotifyEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retry configuration
    public $tries = 3;
    public $maxExceptions = 10;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    protected $default_email = 'negyi.partnership@thanywhere.com';
    protected $mailableClass;
    protected $mail_to;
    protected $mail_subject;
    protected $sent_to_default;
    protected $mail_body;
    protected $booking_item;
    protected $booking_item_group;
    protected $ccEmail;
    protected $email_type;
    protected $email_log_id;
    public $attachments;

    /**
     * Create a new job instance.
     */
    public function __construct($mail_to, $mail_subject, $sent_to_default, $mail_body, BookingItemGroup $booking_item_group, $attachments = null, $ccEmail = null, $email_type = 'booking', $email_log_id = null, $mailableClass = ReservationNotifyEmail::class)
    {
        $this->mail_to = $mail_to;
        $this->mail_subject = $mail_subject;
        $this->sent_to_default = $sent_to_default;
        $this->mail_body = $mail_body;
        $this->booking_item_group = $booking_item_group;
        $this->attachments = $attachments;
        $this->ccEmail = $ccEmail;
        $this->email_type = $email_type;
        $this->email_log_id = $email_log_id;
        $this->mailableClass = $mailableClass; // ✅ $this ထည့်
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->email_log_id) {
                EmailLog::where('id', $this->email_log_id)
                    ->update(['status' => 'sending']);
            }

            $mailable = new $this->mailableClass(
                $this->mail_subject,
                $this->mail_body,
                $this->booking_item_group,
                $this->attachments
            );

            $isHotelMail = $this->mailableClass === ReservationNotifyEmail::class;

            Mail::to($this->getMails())
                ->when(!$isHotelMail, fn($mail) => $mail->cc($this->ccEmail))
                ->send($mailable);

            // ✅ Hotel mail ဆိုရင် trait မှ gmail message id ကို ယူသည်
            // ✅ Default mail ဆိုရင် SentMessage မှ ယူ၍မရသောကြောင့် null ထားသည်
            $gmailMessageId      = null;
            $emailTicketMessageId = null;

            if ($isHotelMail && isset($mailable->syncedGmailMessageId)) {
                $gmailMessageId       = $mailable->syncedGmailMessageId;
                $emailTicketMessageId = $mailable->syncedTicketMessageId;
            }

            if ($this->email_log_id) {
                EmailLog::where('id', $this->email_log_id)->update([
                    'status'     => 'sent',
                    'gmail_message_id' => $gmailMessageId, // ← Gmail Message ID
                ]);

                CustomerDocument::create([
                    'booking_item_group_id' => $this->booking_item_group->id,
                    'type' => $this->email_type === 'booking'
                        ? 'booking_request_proof'
                        : 'expense_mail_proof',
                    'file' => null,
                    'meta' => [
                        'email_log_id'          => $this->email_log_id,
                        'gmail_message_id'      => $gmailMessageId,       // ← Gmail raw ID
                        'email_ticket_message_id' => $emailTicketMessageId, // ← DB record ID
                    ]
                ]);
            }

            $updateData = $this->email_type == 'booking'
                ? ['is_booking_request' => 1]
                : ['is_expense_email_sent' => 1];

            $this->booking_item_group->bookingItems()->update($updateData);

            Log::info('Group notify email sent successfully', [
                'group_id'               => $this->booking_item_group->id,
                'email_log_id'           => $this->email_log_id,
                'gmail_message_id'       => $gmailMessageId,
                'email_ticket_message_id' => $emailTicketMessageId,
            ]);

        } catch (Exception $e) {
            if ($this->email_log_id) {
                EmailLog::where('id', $this->email_log_id)->update([
                    'status'     => 'failed',
                    'plain_body' => ($this->attempts() >= $this->tries)
                        ? strip_tags($this->mail_body) . '\n\nFAIL REASON: ' . $e->getMessage()
                        : strip_tags($this->mail_body) . '\n\nRETRY ATTEMPT: ' . $this->attempts() . '/' . $this->tries
                ]);
            }

            Log::error('Reservation notify email failed', [
                'email_log_id' => $this->email_log_id,
                'group_id'     => $this->booking_item_group->id,
                'error'        => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

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

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        // Update email log with permanent failure
        if ($this->email_log_id) {
            EmailLog::where('id', $this->email_log_id)->update([
                'status' => 'permanently_failed',
                'plain_body' => strip_tags($this->mail_body) . '\n\nPERMANENT FAILURE AFTER ' . $this->tries . ' ATTEMPTS: ' . $exception->getMessage()
            ]);
        }

        Log::error('Reservation notify email permanently failed', [
            'email_log_id' => $this->email_log_id,
            'group_id' => $this->booking_item_group->id,
            'recipients' => $this->getMails(),
            'email_type' => $this->email_type,
            'final_error' => $exception->getMessage()
        ]);
    }
}

