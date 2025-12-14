<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thread_id' => $this->gmail_thread_id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'from' => [
                'email' => $this->from_email,
                'name' => $this->from_name,
                'display' => $this->from_name ? "{$this->from_name} <{$this->from_email}>" : $this->from_email
            ],
            'to' => [
                'email' => $this->to_email,
                'display' => $this->to_email
            ],
            'cc' => $this->cc ? array_map(function ($email) {
                return ['email' => $email, 'display' => $email];
            }, $this->cc) : [],
            'bcc' => $this->bcc ? array_map(function ($email) {
                return ['email' => $email, 'display' => $email];
            }, $this->bcc) : [],
            'subject' => $this->subject,
            'preview' => $this->getPreview(),
            'body' => $this->body,
            'plain_body' => $this->plain_body,
            'status' => $this->status,
            'status_color' => $this->getStatusColor(),
            'is_read' => !is_null($this->read_at),
            'is_reply' => $this->isReply(),
            'has_attachments' => $this->hasAttachments(),
            'attachment_count' => $this->getAttachmentCount(),
            'attachments' => $this->attachments,
            'tags' => $this->tags ?? [],
            'priority' => $this->priority,
            'retry_count' => $this->retry_count,
            'max_retries' => $this->max_retries,
            'is_retryable' => $this->isRetryable(),
            'failure_reason' => $this->failure_reason,
            'gmail_message_id' => $this->gmail_message_id,
            'in_reply_to' => $this->in_reply_to,
            'references' => $this->references,
            'dates' => [
                'created_at' => $this->created_at,
                'sent_at' => $this->sent_at,
                'delivered_at' => $this->delivered_at,
                'read_at' => $this->read_at,
                'failed_at' => $this->failed_at,
                'scheduled_at' => $this->scheduled_at,
                'processed_at' => $this->processed_at
            ],
            'related_booking' => $this->when($this->booking, function () {
                return [
                    'id' => $this->booking->id,
                    'crm_id' => $this->booking->crm_id ?? 'N/A',
                    'customer_name' => $this->booking->customer->name ?? 'N/A'
                ];
            }),
            'related_model' => $this->when($this->relatedModel, function () {
                return [
                    'type' => $this->related_model_type,
                    'id' => $this->related_model_id,
                    'data' => $this->relatedModel
                ];
            })
        ];
    }

    /**
     * Get email preview text
     */
    private function getPreview($length = 150)
    {
        $text = $this->plain_body ?? strip_tags($this->body);

        return \Str::limit($text, $length);
    }
}
