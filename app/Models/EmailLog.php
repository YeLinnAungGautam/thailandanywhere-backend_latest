<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'from_email',
        'from_name',
        'to_email',
        'cc',
        'bcc',
        'subject',
        'body',
        'plain_body',
        'attachments',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_reason',
        'retry_count',
        'max_retries',
        'gmail_message_id',
        'gmail_thread_id',
        'in_reply_to',
        'references',
        'related_booking_id',
        'related_model_type',
        'related_model_id',
        'tags',
        'priority',
        'scheduled_at',
        'processed_at'
    ];

    protected $casts = [
        'cc' => 'array',
        'bcc' => 'array',
        'attachments' => 'array',
        'tags' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'priority' => 'integer'
    ];

    protected $attributes = [
        'retry_count' => 0,
        'max_retries' => 3,
        'status' => 'pending',
        'type' => 'sent',
        'priority' => 1
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class, 'related_booking_id');
    }

    public function relatedModel()
    {
        return $this->morphTo('related_model', 'related_model_type', 'related_model_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForBooking($query, $bookingId)
    {
        return $query->where('related_booking_id', $bookingId);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->whereColumn('retry_count', '<', 'max_retries');
    }

    public function scopeThreaded($query, $threadId)
    {
        return $query->where('gmail_thread_id', $threadId);
    }

    // Helper Methods
    public function isRetryable()
    {
        return $this->status === 'failed' && $this->retry_count < $this->max_retries;
    }

    public function incrementRetry()
    {
        $this->increment('retry_count');

        return $this;
    }

    public function markAsSent($messageId = null, $threadId = null)
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'gmail_message_id' => $messageId,
            'gmail_thread_id' => $threadId,
            'processed_at' => now()
        ]);
    }

    public function markAsFailed($reason)
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
            'processed_at' => now()
        ]);
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now()
        ]);
    }

    public function isRead()
    {
        return !is_null($this->read_at);
    }

    public function hasAttachments()
    {
        return !empty($this->attachments);
    }

    public function getAttachmentCount()
    {
        return is_array($this->attachments) ? count($this->attachments) : 0;
    }

    public function isInThread()
    {
        return !is_null($this->gmail_thread_id);
    }

    public function isReply()
    {
        return !is_null($this->in_reply_to);
    }

    public function getStatusColor()
    {
        return match($this->status) {
            'pending' => 'warning',
            'sent' => 'success',
            'delivered' => 'info',
            'read' => 'primary',
            'failed' => 'danger',
            default => 'secondary'
        };
    }

    public function getTypeLabel()
    {
        return match($this->type) {
            'sent' => 'Sent',
            'received' => 'Received',
            'draft' => 'Draft',
            'template' => 'Template',
            default => ucfirst($this->type)
        };
    }

    // Static methods
    public static function createFromReply($originalEmail, $replyData)
    {
        return static::create([
            'type' => 'received',
            'from_email' => $replyData['from'],
            'to_email' => $originalEmail->from_email,
            'subject' => 'Re: ' . $originalEmail->subject,
            'body' => $replyData['body'],
            'plain_body' => strip_tags($replyData['body']),
            'status' => 'delivered',
            'delivered_at' => now(),
            'gmail_message_id' => $replyData['message_id'] ?? null,
            'gmail_thread_id' => $originalEmail->gmail_thread_id,
            'in_reply_to' => $originalEmail->gmail_message_id,
            'related_booking_id' => $originalEmail->related_booking_id,
            'related_model_type' => $originalEmail->related_model_type,
            'related_model_id' => $originalEmail->related_model_id,
        ]);
    }

    public static function getStatusOptions()
    {
        return [
            'pending' => 'Pending',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'read' => 'Read',
            'failed' => 'Failed'
        ];
    }

    public static function getTypeOptions()
    {
        return [
            'sent' => 'Sent',
            'received' => 'Received',
            'draft' => 'Draft',
            'template' => 'Template'
        ];
    }
}
