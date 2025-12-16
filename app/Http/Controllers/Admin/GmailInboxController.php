<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailResource;
use App\Models\EmailLog;
use App\Services\GmailService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GmailInboxController extends Controller
{
    use HttpResponses;

    protected $gmailService;

    public function __construct()
    {
        $token = Cache::get('gmail_access_token');
        $this->gmailService = new GmailService($token);
    }

    /**
     * Get inbox emails with pagination and filters
     */
    public function inbox(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $status = $request->get('status'); // sent, received, failed, etc.
        $search = $request->get('search');
        $unreadOnly = $request->get('unread_only', false);
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $bookingId = $request->get('booking_id');

        $query = EmailLog::query()
            ->with(['booking', 'relatedModel'])
            ->latest('created_at');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                    ->orWhere('from_email', 'LIKE', "%{$search}%")
                    ->orWhere('to_email', 'LIKE', "%{$search}%")
                    ->orWhere('body', 'LIKE', "%{$search}%");
            });
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($bookingId) {
            $query->where('related_booking_id', $bookingId);
        }

        $emails = $query->paginate($perPage);

        return $this->success([
            'emails' => EmailResource::collection($emails),
            'pagination' => [
                'current_page' => $emails->currentPage(),
                'last_page' => $emails->lastPage(),
                'per_page' => $emails->perPage(),
                'total' => $emails->total(),
                'has_more' => $emails->hasMorePages()
            ],
            'stats' => $this->getInboxStats()
        ], 'Inbox retrieved successfully');
    }

    /**
     * Get email threads (conversations)
     */
    public function threads(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $unreadOnly = $request->get('unread_only', false);

        // Group emails by thread_id or subject for conversation view
        $query = EmailLog::query()
            ->selectRaw('
                COALESCE(gmail_thread_id, CONCAT("thread_", id)) as thread_id,
                COUNT(*) as message_count,
                MAX(created_at) as last_activity,
                MAX(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as has_unread,
                FIRST_VALUE(subject) OVER (PARTITION BY COALESCE(gmail_thread_id, id) ORDER BY created_at) as subject,
                FIRST_VALUE(from_email) OVER (PARTITION BY COALESCE(gmail_thread_id, id) ORDER BY created_at DESC) as last_from_email,
                FIRST_VALUE(type) OVER (PARTITION BY COALESCE(gmail_thread_id, id) ORDER BY created_at DESC) as last_type,
                FIRST_VALUE(id) OVER (PARTITION BY COALESCE(gmail_thread_id, id) ORDER BY created_at DESC) as latest_email_id
            ')
            ->groupByRaw('COALESCE(gmail_thread_id, CONCAT("thread_", id))');

        if ($search) {
            $query->where('subject', 'LIKE', "%{$search}%");
        }

        if ($unreadOnly) {
            $query->havingRaw('MAX(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) = 1');
        }

        $threads = $query->orderBy('last_activity', 'desc')->paginate($perPage);

        return $this->success([
            'threads' => $threads->items(),
            'pagination' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total(),
                'has_more' => $threads->hasMorePages()
            ]
        ], 'Email threads retrieved successfully');
    }

    /**
     * Get specific email thread/conversation
     */
    public function getThread(Request $request, $threadId)
    {
        $emails = EmailLog::where('gmail_thread_id', $threadId)
            ->orWhere('id', str_replace('thread_', '', $threadId))
            ->with(['booking', 'relatedModel'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($emails->isEmpty()) {
            return $this->error(null, 'Thread not found', 404);
        }

        // Mark emails as read when viewing thread
        $emails->whereNull('read_at')->each(function ($email) {
            $email->markAsRead();
        });

        $thread = [
            'thread_id' => $threadId,
            'subject' => $emails->first()->subject,
            'participants' => $emails->pluck('from_email')->concat($emails->pluck('to_email'))->unique()->values(),
            'message_count' => $emails->count(),
            'emails' => EmailResource::collection($emails)
        ];

        return $this->success($thread, 'Thread retrieved successfully');
    }

    /**
     * Send reply to email thread
     */
    public function sendReply(Request $request, $emailId)
    {
        $request->validate([
            'body' => 'required|string',
            'subject' => 'sometimes|string',
            'attachments' => 'sometimes|array'
        ]);

        try {
            $originalEmail = EmailLog::findOrFail($emailId);

            $replyData = [
                'to' => $originalEmail->from_email,
                'subject' => $request->get('subject', 'Re: ' . $originalEmail->subject),
                'body' => $request->body,
                'thread_id' => $originalEmail->gmail_thread_id,
                'in_reply_to' => $originalEmail->gmail_message_id,
                'attachments' => $request->attachments ?? []
            ];

            // Send through Gmail service
            $response = $this->gmailService->sendReply($replyData);

            // Create email log for reply
            $emailLog = EmailLog::create([
                'type' => 'sent',
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_email' => $replyData['to'],
                'subject' => $replyData['subject'],
                'body' => $replyData['body'],
                'plain_body' => strip_tags($replyData['body']),
                'status' => 'sent',
                'sent_at' => now(),
                'gmail_message_id' => $response['message_id'] ?? null,
                'gmail_thread_id' => $response['thread_id'] ?? $originalEmail->gmail_thread_id,
                'in_reply_to' => $originalEmail->gmail_message_id,
                'related_booking_id' => $originalEmail->related_booking_id,
                'related_model_type' => $originalEmail->related_model_type,
                'related_model_id' => $originalEmail->related_model_id,
                'attachments' => !empty($replyData['attachments']) ? json_encode($replyData['attachments']) : null
            ]);

            return $this->success([
                'email' => new EmailResource($emailLog),
                'message_id' => $response['message_id'] ?? null
            ], 'Reply sent successfully');

        } catch (Exception $e) {
            Log::error('Failed to send reply: ' . $e->getMessage());

            return $this->error(null, 'Failed to send reply: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Compose new email
     */
    public function compose(Request $request)
    {
        $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string',
            'body' => 'required|string',
            'cc' => 'sometimes|array',
            'cc.*' => 'email',
            'bcc' => 'sometimes|array',
            'bcc.*' => 'email',
            'attachments' => 'sometimes|array',
            'booking_id' => 'sometimes|integer|exists:bookings,id',
            'related_model_type' => 'sometimes|string',
            'related_model_id' => 'sometimes|integer'
        ]);

        try {
            $emailData = [
                'to' => $request->to,
                'cc' => $request->cc ?? [],
                'bcc' => $request->bcc ?? [],
                'subject' => $request->subject,
                'body' => $request->body,
                'attachments' => $request->attachments ?? []
            ];

            // Send through Gmail service
            $response = $this->gmailService->sendEmail($emailData);

            // Create email log
            $emailLog = EmailLog::create([
                'type' => 'sent',
                'from_email' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_email' => $request->to,
                'cc' => !empty($request->cc) ? json_encode($request->cc) : null,
                'bcc' => !empty($request->bcc) ? json_encode($request->bcc) : null,
                'subject' => $request->subject,
                'body' => $request->body,
                'plain_body' => strip_tags($request->body),
                'status' => 'sent',
                'sent_at' => now(),
                'gmail_message_id' => $response['message_id'] ?? null,
                'gmail_thread_id' => $response['thread_id'] ?? null,
                'related_booking_id' => $request->booking_id,
                'related_model_type' => $request->related_model_type,
                'related_model_id' => $request->related_model_id,
                'attachments' => !empty($request->attachments) ? json_encode($request->attachments) : null
            ]);

            return $this->success([
                'email' => new EmailResource($emailLog),
                'message_id' => $response['message_id'] ?? null
            ], 'Email sent successfully');

        } catch (Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());

            return $this->error(null, 'Failed to send email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark emails as read
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'email_ids' => 'required|array',
            'email_ids.*' => 'integer|exists:email_logs,id'
        ]);

        $updated = EmailLog::whereIn('id', $request->email_ids)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success([
            'updated_count' => $updated
        ], 'Emails marked as read');
    }

    /**
     * Mark emails as unread
     */
    public function markAsUnread(Request $request)
    {
        $request->validate([
            'email_ids' => 'required|array',
            'email_ids.*' => 'integer|exists:email_logs,id'
        ]);

        $updated = EmailLog::whereIn('id', $request->email_ids)
            ->update(['read_at' => null]);

        return $this->success([
            'updated_count' => $updated
        ], 'Emails marked as unread');
    }

    /**
     * Archive emails
     */
    public function archive(Request $request)
    {
        $request->validate([
            'email_ids' => 'required|array',
            'email_ids.*' => 'integer|exists:email_logs,id'
        ]);

        $updated = EmailLog::whereIn('id', $request->email_ids)
            ->update([
                'tags' => \DB::raw("JSON_ARRAY_APPEND(COALESCE(tags, JSON_ARRAY()), '$', 'archived')")
            ]);

        return $this->success([
            'archived_count' => $updated
        ], 'Emails archived successfully');
    }

    /**
     * Delete emails (soft delete)
     */
    public function delete(Request $request)
    {
        $request->validate([
            'email_ids' => 'required|array',
            'email_ids.*' => 'integer|exists:email_logs,id'
        ]);

        $deleted = EmailLog::whereIn('id', $request->email_ids)->delete();

        return $this->success([
            'deleted_count' => $deleted
        ], 'Emails deleted successfully');
    }

    /**
     * Sync emails from Gmail
     */
    public function syncFromGmail(Request $request)
    {
        $limit = $request->get('limit', 50);

        try {
            $syncedCount = $this->gmailService->syncRecentEmails($limit);

            return $this->success([
                'synced_count' => $syncedCount
            ], 'Gmail sync completed successfully');

        } catch (Exception $e) {
            Log::error('Gmail sync failed: ' . $e->getMessage());

            return $this->error(null, 'Gmail sync failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get inbox statistics
     */
    public function getInboxStats()
    {
        $stats = [
            'total_emails' => EmailLog::count(),
            'unread_count' => EmailLog::whereNull('read_at')->count(),
            'sent_count' => EmailLog::where('type', 'sent')->count(),
            'received_count' => EmailLog::where('type', 'received')->count(),
            'failed_count' => EmailLog::where('status', 'failed')->count(),
            'today_count' => EmailLog::whereDate('created_at', today())->count(),
            'this_week_count' => EmailLog::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count()
        ];

        return $stats;
    }

    /**
     * Transform email for inbox display
     */
    private function transformEmailForInbox($email)
    {
        return [
            'id' => $email->id,
            'thread_id' => $email->gmail_thread_id,
            'type' => $email->type,
            'from_email' => $email->from_email,
            'from_name' => $email->from_name,
            'to_email' => $email->to_email,
            'cc' => $email->cc,
            'subject' => $email->subject,
            'preview' => $this->getEmailPreview($email->plain_body ?? strip_tags($email->body)),
            'status' => $email->status,
            'is_read' => !is_null($email->read_at),
            'has_attachments' => $email->hasAttachments(),
            'attachment_count' => $email->getAttachmentCount(),
            'is_reply' => $email->isReply(),
            'created_at' => $email->created_at,
            'sent_at' => $email->sent_at,
            'read_at' => $email->read_at,
            'booking' => $email->booking ? [
                'id' => $email->booking->id,
                'crm_id' => $email->booking->crm_id ?? 'N/A'
            ] : null
        ];
    }

    /**
     * Transform email for thread display
     */
    private function transformEmailForThread($email)
    {
        return [
            'id' => $email->id,
            'type' => $email->type,
            'from_email' => $email->from_email,
            'from_name' => $email->from_name,
            'to_email' => $email->to_email,
            'cc' => $email->cc,
            'bcc' => $email->bcc,
            'subject' => $email->subject,
            'body' => $email->body,
            'plain_body' => $email->plain_body,
            'attachments' => $email->attachments,
            'status' => $email->status,
            'is_read' => !is_null($email->read_at),
            'gmail_message_id' => $email->gmail_message_id,
            'created_at' => $email->created_at,
            'sent_at' => $email->sent_at,
            'read_at' => $email->read_at
        ];
    }

    /**
     * Get email preview text
     */
    private function getEmailPreview($text, $length = 150)
    {
        return \Str::limit(strip_tags($text), $length);
    }
}
