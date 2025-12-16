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

class EmailLogController extends Controller
{
    use HttpResponses;

    /**
     * Display a listing of email logs
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $status = $request->get('status');
        $type = $request->get('type');
        $search = $request->get('search');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $bookingId = $request->get('booking_id');

        $query = EmailLog::query()
            ->with(['booking', 'relatedModel'])
            ->latest('created_at');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                    ->orWhere('from_email', 'LIKE', "%{$search}%")
                    ->orWhere('to_email', 'LIKE', "%{$search}%")
                    ->orWhere('body', 'LIKE', "%{$search}%");
            });
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
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
            'filters' => [
                'statuses' => EmailLog::getStatusOptions(),
                'types' => EmailLog::getTypeOptions()
            ]
        ], 'Email logs retrieved successfully');
    }

    /**
     * Store a new email log
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:sent,received,draft,template',
            'to_email' => 'required|email',
            'subject' => 'required|string',
            'body' => 'required|string',
            'from_email' => 'sometimes|email',
            'cc' => 'sometimes|array',
            'cc.*' => 'email',
            'bcc' => 'sometimes|array',
            'bcc.*' => 'email',
            'send_immediately' => 'sometimes|boolean',
            'scheduled_at' => 'sometimes|date',
            'priority' => 'sometimes|integer|between:1,3',
            'related_booking_id' => 'sometimes|integer|exists:bookings,id',
            'related_model_type' => 'sometimes|string',
            'related_model_id' => 'sometimes|integer'
        ]);

        try {
            $emailData = [
                'type' => $request->type,
                'from_email' => $request->from_email ?? config('mail.from.address'),
                'from_name' => $request->from_name ?? config('mail.from.name'),
                'to_email' => $request->to_email,
                'cc' => $request->cc ? json_encode($request->cc) : null,
                'bcc' => $request->bcc ? json_encode($request->bcc) : null,
                'subject' => $request->subject,
                'body' => $request->body,
                'plain_body' => strip_tags($request->body),
                'status' => $request->send_immediately ? 'pending' : 'draft',
                'priority' => $request->priority ?? 1,
                'scheduled_at' => $request->scheduled_at,
                'related_booking_id' => $request->related_booking_id,
                'related_model_type' => $request->related_model_type,
                'related_model_id' => $request->related_model_id,
            ];

            $emailLog = EmailLog::create($emailData);

            // If send immediately, dispatch to Gmail
            if ($request->send_immediately && $request->type === 'sent') {
                $this->sendEmailViaGmail($emailLog);
            }

            return $this->success(
                new EmailResource($emailLog),
                'Email log created successfully'
            );

        } catch (Exception $e) {
            Log::error('Failed to create email log: ' . $e->getMessage());

            return $this->error(null, 'Failed to create email log: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified email log
     */
    public function show(EmailLog $emailLog)
    {
        $emailLog->load(['booking', 'relatedModel']);

        // Mark as read when viewing
        if (!$emailLog->isRead() && $emailLog->type === 'received') {
            $emailLog->markAsRead();
        }

        return $this->success(
            new EmailResource($emailLog),
            'Email log retrieved successfully'
        );
    }

    /**
     * Update the specified email log
     */
    public function update(Request $request, EmailLog $emailLog)
    {
        $request->validate([
            'subject' => 'sometimes|string',
            'body' => 'sometimes|string',
            'status' => 'sometimes|in:pending,sent,delivered,read,failed,draft',
            'tags' => 'sometimes|array',
            'priority' => 'sometimes|integer|between:1,3',
            'scheduled_at' => 'sometimes|date',
        ]);

        try {
            $updateData = $request->only([
                'subject', 'body', 'status', 'priority', 'scheduled_at'
            ]);

            if ($request->has('body')) {
                $updateData['plain_body'] = strip_tags($request->body);
            }

            if ($request->has('tags')) {
                $updateData['tags'] = json_encode($request->tags);
            }

            $emailLog->update($updateData);

            return $this->success(
                new EmailResource($emailLog),
                'Email log updated successfully'
            );

        } catch (Exception $e) {
            Log::error('Failed to update email log: ' . $e->getMessage());

            return $this->error(null, 'Failed to update email log: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified email log (soft delete)
     */
    public function destroy(EmailLog $emailLog)
    {
        try {
            $emailLog->delete();

            return $this->success(null, 'Email log deleted successfully');

        } catch (Exception $e) {
            Log::error('Failed to delete email log: ' . $e->getMessage());

            return $this->error(null, 'Failed to delete email log: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark specific email as read
     */
    public function markAsRead(EmailLog $emailLog)
    {
        try {
            $emailLog->markAsRead();

            return $this->success(
                new EmailResource($emailLog),
                'Email marked as read'
            );

        } catch (Exception $e) {
            Log::error('Failed to mark email as read: ' . $e->getMessage());

            return $this->error(null, 'Failed to mark email as read: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark multiple emails as read
     */
    public function bulkMarkAsRead(Request $request)
    {
        $request->validate([
            'email_ids' => 'required|array',
            'email_ids.*' => 'integer|exists:email_logs,id'
        ]);

        try {
            $updated = EmailLog::whereIn('id', $request->email_ids)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return $this->success([
                'updated_count' => $updated
            ], 'Emails marked as read successfully');

        } catch (Exception $e) {
            Log::error('Failed to bulk mark emails as read: ' . $e->getMessage());

            return $this->error(null, 'Failed to mark emails as read: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get email statistics
     */
    public function getStats()
    {
        try {
            $stats = [
                'total_emails' => EmailLog::count(),
                'sent_emails' => EmailLog::where('type', 'sent')->count(),
                'received_emails' => EmailLog::where('type', 'received')->count(),
                'pending_emails' => EmailLog::where('status', 'pending')->count(),
                'failed_emails' => EmailLog::where('status', 'failed')->count(),
                'unread_emails' => EmailLog::whereNull('read_at')->count(),
                'today_emails' => EmailLog::whereDate('created_at', today())->count(),
                'this_week_emails' => EmailLog::whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count(),
                'this_month_emails' => EmailLog::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count()
            ];

            // Status breakdown
            $statusBreakdown = EmailLog::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Recent activity (last 7 days)
            $recentActivity = EmailLog::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return $this->success([
                'stats' => $stats,
                'status_breakdown' => $statusBreakdown,
                'recent_activity' => $recentActivity
            ], 'Email statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('Failed to get email statistics: ' . $e->getMessage());

            return $this->error(null, 'Failed to get email statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search emails
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'per_page' => 'sometimes|integer|between:1,100'
        ]);

        $perPage = $request->get('per_page', 20);
        $query = $request->get('query');

        try {
            $emails = EmailLog::where(function ($q) use ($query) {
                $q->where('subject', 'LIKE', "%{$query}%")
                    ->orWhere('from_email', 'LIKE', "%{$query}%")
                    ->orWhere('to_email', 'LIKE', "%{$query}%")
                    ->orWhere('body', 'LIKE', "%{$query}%")
                    ->orWhere('plain_body', 'LIKE', "%{$query}%");
            })
                ->with(['booking', 'relatedModel'])
                ->latest('created_at')
                ->paginate($perPage);

            return $this->success([
                'emails' => EmailResource::collection($emails),
                'pagination' => [
                    'current_page' => $emails->currentPage(),
                    'last_page' => $emails->lastPage(),
                    'per_page' => $emails->perPage(),
                    'total' => $emails->total(),
                    'has_more' => $emails->hasMorePages()
                ],
                'query' => $query
            ], 'Search results retrieved successfully');

        } catch (Exception $e) {
            Log::error('Email search failed: ' . $e->getMessage());

            return $this->error(null, 'Email search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send email via Gmail service
     */
    private function sendEmailViaGmail(EmailLog $emailLog)
    {
        try {
            $token = Cache::get('gmail_access_token');
            if (!$token) {
                throw new Exception('Gmail not configured');
            }

            $gmailService = new GmailService($token);

            $emailData = [
                'to' => $emailLog->to_email,
                'cc' => $emailLog->cc ? json_decode($emailLog->cc, true) : [],
                'bcc' => $emailLog->bcc ? json_decode($emailLog->bcc, true) : [],
                'subject' => $emailLog->subject,
                'body' => $emailLog->body
            ];

            $response = $gmailService->sendEmail($emailData);

            $emailLog->update([
                'status' => 'sent',
                'sent_at' => now(),
                'gmail_message_id' => $response['message_id'] ?? null,
                'gmail_thread_id' => $response['thread_id'] ?? null,
                'processed_at' => now()
            ]);

        } catch (Exception $e) {
            $emailLog->markAsFailed($e->getMessage());
            Log::error('Failed to send email via Gmail: ' . $e->getMessage());
        }
    }
}
