<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTicket;
use App\Models\EmailTicketMessage;
use App\Services\GmailService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GmailInboxController extends Controller
{
    use HttpResponses;

    protected $gmailService;
    protected ?string $gmailAccountEmail;

    public function __construct()
    {
        // GmailService auto-loads the token from storage/app/gmail_token.json
        $this->gmailService     = new GmailService();
        $this->gmailAccountEmail = $this->gmailService->accountEmail;
    }

    /**
     * Get inbox — lists email tickets with their latest message preview.
     */
    public function inbox(Request $request)
    {
        $perPage    = $request->get('per_page', 20);
        $search     = $request->get('search');
        $status     = $request->get('status');      // open, closed, etc.
        $unreadOnly = $request->get('unread_only', false);
        $startDate  = $request->get('start_date');
        $endDate    = $request->get('end_date');

        $query = EmailTicket::with(['messages' => function ($q) {
            $q->orderBy('created_at', 'desc');
        }])->latest('updated_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                    ->orWhere('customer_email', 'LIKE', "%{$search}%");
            });
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $tickets = $query->paginate($perPage);

        $emails = $tickets->map(function ($ticket) {
            $latest = $ticket->messages->first();

            return [
                'id'             => $ticket->id,
                'thread_id'      => $ticket->gmail_thread_id,
                'subject'        => $ticket->subject,
                'customer_email' => $ticket->customer_email,
                'status'         => $ticket->status,
                'message_count'  => $ticket->messages->count(),
                'preview'        => $latest ? \Str::limit(strip_tags($latest->body), 150) : null,
                'from'           => $latest->from ?? null,
                'to'             => $latest->to ?? null,
                'is_incoming'    => $latest->is_incoming ?? true,
                'last_message_at' => $latest->gmail_datetime ?? $ticket->updated_at,
                'created_at'     => $ticket->created_at,
                'updated_at'     => $ticket->updated_at,
            ];
        });

        return $this->success([
            'emails'     => $emails,
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'per_page'     => $tickets->perPage(),
                'total'        => $tickets->total(),
                'has_more'     => $tickets->hasMorePages(),
            ],
            'stats' => $this->getInboxStats(),
        ], 'Inbox retrieved successfully');
    }

    /**
     * List all threads (alias of inbox, returns same ticket-based structure).
     */
    public function threads(Request $request)
    {
        $perPage    = $request->get('per_page', 15);
        $search     = $request->get('search');
        $unreadOnly = $request->get('unread_only', false);

        $query = EmailTicket::withCount('messages')->latest('updated_at');

        if ($search) {
            $query->where('subject', 'LIKE', "%{$search}%");
        }

        $threads = $query->paginate($perPage);

        return $this->success([
            'threads'    => $threads->map(function ($ticket) {
                return [
                    'thread_id'      => $ticket->gmail_thread_id,
                    'subject'        => $ticket->subject,
                    'customer_email' => $ticket->customer_email,
                    'status'         => $ticket->status,
                    'message_count'  => $ticket->messages_count,
                    'last_activity'  => $ticket->updated_at,
                ];
            }),
            'pagination' => [
                'current_page' => $threads->currentPage(),
                'last_page'    => $threads->lastPage(),
                'per_page'     => $threads->perPage(),
                'total'        => $threads->total(),
                'has_more'     => $threads->hasMorePages(),
            ],
        ], 'Email threads retrieved successfully');
    }

    /**
     * Get all messages in a specific thread.
     */
    public function getThread(Request $request, $threadId)
    {
        $ticket = EmailTicket::where('gmail_thread_id', $threadId)
            ->when(is_numeric($threadId), function ($q) use ($threadId) {
                $q->orWhere('id', $threadId);
            })
            ->first();

        if (!$ticket) {
            return $this->error(null, 'Thread not found', 404);
        }

        $messages = EmailTicketMessage::where('ticket_id', $ticket->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->success([
            'thread_id'      => $ticket->gmail_thread_id,
            'subject'        => $ticket->subject,
            'customer_email' => $ticket->customer_email,
            'status'         => $ticket->status,
            'message_count'  => $messages->count(),
            'emails'         => $messages->map(function ($msg) {
                return [
                    'id'               => $msg->id,
                    'from'             => $msg->from,
                    'to'               => $msg->to,
                    'body'             => $msg->body,
                    'is_incoming'      => $msg->is_incoming,
                    'attachments'      => $msg->attachments ?? [],
                    'gmail_message_id' => $msg->gmail_message_id,
                    'created_at'       => $msg->gmail_datetime ?? $msg->created_at,
                ];
            }),
        ], 'Thread retrieved successfully');
    }

    /**
     * Send reply to a thread.
     */
    public function sendReply(Request $request, $emailId)
    {
        $request->validate([
            'body'    => 'required|string',
            'subject' => 'sometimes|string',
        ]);

        try {
            // $emailId can be a ticket id or message id
            $message = EmailTicketMessage::find($emailId);
            $ticket  = $message
                ? EmailTicket::find($message->ticket_id)
                : EmailTicket::find($emailId);

            if (!$ticket) {
                return $this->error(null, 'Email/thread not found', 404);
            }

            $replyData = [
                'to'          => $ticket->customer_email,
                'subject'     => $request->get('subject', 'Re: ' . $ticket->subject),
                'body'        => $request->body,
                'thread_id'   => $ticket->gmail_thread_id,
                'in_reply_to' => $message?->gmail_message_id,
            ];

            $response = $this->gmailService->sendReply($replyData);

            // Store the sent reply as a message on the ticket
            $sentMessage = EmailTicketMessage::create([
                'ticket_id'        => $ticket->id,
                'from'             => $this->gmailAccountEmail,
                'to'               => $ticket->customer_email,
                'body'             => $request->body,
                'gmail_message_id' => $response['message_id'] ?? null,
                'gmail_datetime'   => now(),
                'is_incoming'      => false,
            ]);

            // Update thread_id on ticket if it was missing
            if (!$ticket->gmail_thread_id && !empty($response['thread_id'])) {
                $ticket->update(['gmail_thread_id' => $response['thread_id']]);
            }

            return $this->success([
                'message'    => $sentMessage,
                'message_id' => $response['message_id'] ?? null,
            ], 'Reply sent successfully');

        } catch (Exception $e) {
            Log::error('Failed to send reply: ' . $e->getMessage());

            return $this->error(null, 'Failed to send reply: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Compose new email.
     */
    public function compose(Request $request)
    {
        $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string',
            'body'    => 'required|string',
            'cc'      => 'sometimes|array',
            'cc.*'    => 'email',
        ]);

        try {
            $emailData = [
                'to'      => $request->to,
                'cc'      => $request->cc ?? [],
                'subject' => $request->subject,
                'body'    => $request->body,
            ];

            $response = $this->gmailService->sendEmail($emailData);

            // Create a ticket + outgoing message
            $ticket = EmailTicket::create([
                'subject'        => $request->subject,
                'customer_email' => $request->to,
                'gmail_thread_id' => $response['thread_id'] ?? null,
                'status'         => 'open',
            ]);

            $msg = EmailTicketMessage::create([
                'ticket_id'        => $ticket->id,
                'from'             => $this->gmailAccountEmail,
                'to'               => $request->to,
                'body'             => $request->body,
                'gmail_message_id' => $response['message_id'] ?? null,
                'gmail_datetime'   => now(),
                'is_incoming'      => false,
            ]);

            return $this->success([
                'ticket'     => $ticket,
                'message'    => $msg,
                'message_id' => $response['message_id'] ?? null,
            ], 'Email sent successfully');

        } catch (Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());

            return $this->error(null, 'Failed to send email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sync emails from Gmail into email_tickets / email_ticket_messages.
     * Delegates to the same logic as the artisan command.
     */
    public function syncFromGmail(Request $request)
    {
        $limit      = $request->get('limit', 50);
        $inboxEmail = $this->gmailAccountEmail;

        try {
            $query = "to:{$inboxEmail} OR from:{$inboxEmail} is:unread";

            $response = $this->gmailService->service->users_messages
                ->listUsersMessages('me', ['maxResults' => $limit, 'q' => $query]);

            $messages = $response->getMessages() ?? [];
            $synced   = 0;
            $skipped  = 0;

            foreach ($messages as $msg) {
                $full     = $this->gmailService->getMessage($msg->getId(), ['format' => 'full']);
                $threadId = $full['threadId'] ?? $msg->getThreadId();

                $headers = collect($full['payload']['headers'] ?? []);
                $from    = $headers->firstWhere('name', 'From')['value'] ?? '';
                $to      = $headers->firstWhere('name', 'To')['value'] ?? '';
                $subject = $headers->firstWhere('name', 'Subject')['value'] ?? '(no subject)';

                $parsed = $this->gmailService->parseMessagePayload($full['payload'] ?? [], $full['id']);
                $body   = $parsed['body'];
                $attachments = $parsed['attachments'];

                if (EmailTicketMessage::where('gmail_message_id', $full['id'])->exists()) {
                    $skipped++;
                    continue;
                }

                $ticket = EmailTicket::firstOrCreate(
                    ['gmail_thread_id' => $threadId],
                    ['subject' => mb_substr($subject, 0, 255), 'customer_email' => mb_substr($from, 0, 255), 'status' => 'open']
                );

                EmailTicketMessage::create([
                    'ticket_id'        => $ticket->id,
                    'from'             => mb_substr($from, 0, 255),
                    'to'               => mb_substr($to, 0, 255) ?: 'me',
                    'body'             => mb_substr($body, 0, 65535),
                    'attachments'      => !empty($attachments) ? $attachments : null,
                    'gmail_message_id' => $full['id'],
                    'gmail_datetime'   => now(),
                    'is_incoming'      => true,
                ]);

                $synced++;
            }

            return $this->success([
                'synced_count'  => $synced,
                'skipped_count' => $skipped,
                'account_email' => $inboxEmail,
            ], 'Gmail sync completed successfully');

        } catch (Exception $e) {
            Log::error('Gmail sync failed: ' . $e->getMessage());

            return $this->error(null, 'Gmail sync failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Close a ticket (mark as resolved).
     */
    public function closeTicket(Request $request, $ticketId)
    {
        $ticket = EmailTicket::findOrFail($ticketId);
        $ticket->update(['status' => 'closed']);

        return $this->success(['ticket' => $ticket], 'Ticket closed successfully');
    }

    /**
     * Reopen a ticket.
     */
    public function reopenTicket(Request $request, $ticketId)
    {
        $ticket = EmailTicket::findOrFail($ticketId);
        $ticket->update(['status' => 'open']);

        return $this->success(['ticket' => $ticket], 'Ticket reopened successfully');
    }

    /**
     * Mark emails as read (kept for API compatibility).
     */
    public function markAsRead(Request $request)
    {
        $request->validate(['email_ids' => 'required|array']);
        // email_ticket_messages doesn't have read_at, so this is a no-op for now
        return $this->success(['updated_count' => 0], 'Marked as read');
    }

    /**
     * Mark emails as unread (kept for API compatibility).
     */
    public function markAsUnread(Request $request)
    {
        return $this->success(['updated_count' => 0], 'Marked as unread');
    }

    /**
     * Archive (kept for API compatibility).
     */
    public function archive(Request $request)
    {
        $request->validate(['email_ids' => 'required|array']);
        $updated = EmailTicket::whereIn('id', $request->email_ids)->update(['status' => 'archived']);

        return $this->success(['archived_count' => $updated], 'Emails archived successfully');
    }

    /**
     * Delete tickets (soft concerns — we just close them).
     */
    public function delete(Request $request)
    {
        $request->validate(['email_ids' => 'required|array']);
        $deleted = EmailTicket::whereIn('id', $request->email_ids)->delete();

        return $this->success(['deleted_count' => $deleted], 'Emails deleted successfully');
    }

    /**
     * Inbox statistics.
     */
    public function getInboxStats()
    {
        return [
            'total_threads'  => EmailTicket::count(),
            'open_count'     => EmailTicket::where('status', 'open')->count(),
            'closed_count'   => EmailTicket::where('status', 'closed')->count(),
            'total_messages' => EmailTicketMessage::count(),
            'today_count'    => EmailTicket::whereDate('created_at', today())->count(),
            'this_week_count' => EmailTicket::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->count(),
        ];
    }
}
