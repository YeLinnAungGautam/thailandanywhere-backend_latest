<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendReservationNotifyEmailJob;
use App\Models\BookingItem;
use App\Models\EmailLog;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailStatusController extends Controller
{
    use HttpResponses;

    /**
     * Get email status for a specific booking item
     */
    public function getEmailStatus(Request $request, $bookingItemId)
    {
        $bookingItem = BookingItem::findOrFail($bookingItemId);

        $emails = EmailLog::where('related_model_type', 'App\Models\BookingItem')
            ->where('related_model_id', $bookingItemId)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total' => $emails->count(),
            'sent' => $emails->where('status', 'sent')->count(),
            'failed' => $emails->whereIn('status', ['failed', 'permanently_failed'])->count(),
            'pending' => $emails->whereIn('status', ['pending', 'sending'])->count(),
        ];

        return $this->success([
            'emails' => $emails,
            'stats' => $stats,
            'booking_item' => [
                'id' => $bookingItem->id,
                'crm_id' => $bookingItem->crm_id,
                'is_booking_request' => $bookingItem->is_booking_request,
                'is_expense_email_sent' => $bookingItem->is_expense_email_sent,
            ]
        ], 'Email status retrieved successfully');
    }

    /**
     * Retry a failed email
     */
    public function retryEmail(Request $request, $emailLogId)
    {
        $emailLog = EmailLog::findOrFail($emailLogId);

        // Check if email can be retried
        if (!in_array($emailLog->status, ['failed', 'permanently_failed'])) {
            return $this->error(null, 'Email cannot be retried. Current status: ' . $emailLog->status, 400);
        }

        try {
            // Get related booking item
            $bookingItem = BookingItem::find($emailLog->related_model_id);
            if (!$bookingItem) {
                return $this->error(null, 'Related booking item not found', 404);
            }

            // Reset email log status
            $emailLog->update([
                'status' => 'pending',
                'plain_body' => strip_tags($emailLog->body) . "\n\nMANUAL RETRY INITIATED"
            ]);

            // Determine email type based on flags or content
            $emailType = 'booking';
            if (stripos($emailLog->subject, 'expense') !== false ||
                stripos($emailLog->body, 'expense') !== false) {
                $emailType = 'expense';
            }

            // Parse CC emails
            $ccEmails = json_decode($emailLog->cc, true);
            $ccEmail = is_array($ccEmails) && count($ccEmails) > 0 ? $ccEmails[0] : null;

            // Parse attachments
            $attachments = json_decode($emailLog->attachments, true);

            // Dispatch new job
            dispatch(new SendReservationNotifyEmailJob(
                $emailLog->to_email,
                $emailLog->subject,
                false, // sent_to_default
                $emailLog->body,
                $bookingItem,
                $attachments,
                $ccEmail,
                $emailType,
                $emailLog->id
            ));

            Log::info('Email retry initiated', [
                'email_log_id' => $emailLog->id,
                'booking_item_id' => $bookingItem->id,
                'to_email' => $emailLog->to_email
            ]);

            return $this->success([
                'email_log_id' => $emailLog->id,
                'status' => 'pending'
            ], 'Email retry initiated successfully');

        } catch (Exception $e) {
            Log::error('Email retry failed', [
                'email_log_id' => $emailLogId,
                'error' => $e->getMessage()
            ]);

            return $this->error(null, 'Failed to retry email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retry all failed emails for a booking item
     */
    public function retryAllFailed(Request $request, $bookingItemId)
    {
        $bookingItem = BookingItem::findOrFail($bookingItemId);

        $failedEmails = EmailLog::where('related_model_type', 'App\Models\BookingItem')
            ->where('related_model_id', $bookingItemId)
            ->whereIn('status', ['failed', 'permanently_failed'])
            ->get();

        if ($failedEmails->isEmpty()) {
            return $this->error(null, 'No failed emails found for this booking item', 404);
        }

        $retried = [];
        $errors = [];

        foreach ($failedEmails as $emailLog) {
            try {
                // Reset email log status
                $emailLog->update([
                    'status' => 'pending',
                    'plain_body' => strip_tags($emailLog->body) . "\n\nBULK RETRY INITIATED"
                ]);

                // Determine email type
                $emailType = 'booking';
                if (stripos($emailLog->subject, 'expense') !== false ||
                    stripos($emailLog->body, 'expense') !== false) {
                    $emailType = 'expense';
                }

                // Parse CC emails
                $ccEmails = json_decode($emailLog->cc, true);
                $ccEmail = is_array($ccEmails) && count($ccEmails) > 0 ? $ccEmails[0] : null;

                // Parse attachments
                $attachments = json_decode($emailLog->attachments, true);

                // Dispatch new job
                dispatch(new SendReservationNotifyEmailJob(
                    $emailLog->to_email,
                    $emailLog->subject,
                    false,
                    $emailLog->body,
                    $bookingItem,
                    $attachments,
                    $ccEmail,
                    $emailType,
                    $emailLog->id
                ));

                $retried[] = $emailLog->id;

            } catch (Exception $e) {
                $errors[] = [
                    'email_log_id' => $emailLog->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        Log::info('Bulk email retry completed', [
            'booking_item_id' => $bookingItemId,
            'total_failed' => $failedEmails->count(),
            'retried' => count($retried),
            'errors' => count($errors)
        ]);

        return $this->success([
            'total_failed' => $failedEmails->count(),
            'retried' => count($retried),
            'retried_ids' => $retried,
            'errors' => $errors
        ], 'Bulk retry completed');
    }

    /**
     * Get email statistics
     */
    public function getEmailStats(Request $request)
    {
        $query = EmailLog::query();

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Filter by booking item if provided
        if ($request->has('booking_item_id')) {
            $query->where('related_model_type', 'App\Models\BookingItem')
                ->where('related_model_id', $request->booking_item_id);
        }

        $stats = [
            'total' => $query->count(),
            'sent' => $query->where('status', 'sent')->count(),
            'failed' => $query->whereIn('status', ['failed', 'permanently_failed'])->count(),
            'pending' => $query->whereIn('status', ['pending', 'sending'])->count(),
            'success_rate' => 0
        ];

        if ($stats['total'] > 0) {
            $stats['success_rate'] = round(($stats['sent'] / $stats['total']) * 100, 2);
        }

        // Get recent failures
        $recentFailures = EmailLog::whereIn('status', ['failed', 'permanently_failed'])
            ->when($request->has('booking_item_id'), function ($q) use ($request) {
                $q->where('related_model_type', 'App\Models\BookingItem')
                    ->where('related_model_id', $request->booking_item_id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'to_email', 'subject', 'status', 'created_at']);

        return $this->success([
            'stats' => $stats,
            'recent_failures' => $recentFailures
        ], 'Email statistics retrieved successfully');
    }
}
