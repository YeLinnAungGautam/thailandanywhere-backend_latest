<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailThreadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $emails = collect($this->resource['emails'] ?? []);
        $latestEmail = $emails->last();
        $firstEmail = $emails->first();

        return [
            'thread_id' => $this->resource['thread_id'],
            'subject' => $this->resource['subject'],
            'message_count' => $emails->count(),
            'participants' => $this->getParticipants($emails),
            'preview' => $this->getThreadPreview($emails),
            'has_unread' => $emails->whereNull('read_at')->count() > 0,
            'unread_count' => $emails->whereNull('read_at')->count(),
            'has_attachments' => $emails->where('attachments', '!=', null)->count() > 0,
            'latest_message' => [
                'id' => $latestEmail['id'] ?? null,
                'from_email' => $latestEmail['from_email'] ?? '',
                'from_name' => $latestEmail['from_name'] ?? '',
                'type' => $latestEmail['type'] ?? '',
                'status' => $latestEmail['status'] ?? '',
                'created_at' => $latestEmail['created_at'] ?? null,
                'is_read' => !is_null($latestEmail['read_at'] ?? null)
            ],
            'first_message' => [
                'id' => $firstEmail['id'] ?? null,
                'from_email' => $firstEmail['from_email'] ?? '',
                'from_name' => $firstEmail['from_name'] ?? '',
                'created_at' => $firstEmail['created_at'] ?? null
            ],
            'dates' => [
                'started_at' => $firstEmail['created_at'] ?? null,
                'last_activity' => $latestEmail['created_at'] ?? null
            ],
            'tags' => $this->getThreadTags($emails),
            'related_booking' => $this->getRelatedBooking($emails),
            'emails' => EmailResource::collection($emails)
        ];
    }

    /**
     * Get unique participants in the thread
     */
    private function getParticipants($emails)
    {
        $participants = collect();

        foreach ($emails as $email) {
            $participants->push([
                'email' => $email['from_email'],
                'name' => $email['from_name'],
                'display' => $email['from_name'] ? "{$email['from_name']} <{$email['from_email']}>" : $email['from_email']
            ]);

            $participants->push([
                'email' => $email['to_email'],
                'name' => null,
                'display' => $email['to_email']
            ]);
        }

        return $participants->unique('email')->values();
    }

    /**
     * Get thread preview from latest message
     */
    private function getThreadPreview($emails, $length = 150)
    {
        $latestEmail = $emails->last();
        if (!$latestEmail) {
            return '';
        }

        $text = $latestEmail['plain_body'] ?? strip_tags($latestEmail['body'] ?? '');

        return \Str::limit($text, $length);
    }

    /**
     * Get aggregated tags from all emails in thread
     */
    private function getThreadTags($emails)
    {
        $tags = collect();

        foreach ($emails as $email) {
            if (!empty($email['tags'])) {
                $tags = $tags->merge($email['tags']);
            }
        }

        return $tags->unique()->values();
    }

    /**
     * Get related booking information from thread
     */
    private function getRelatedBooking($emails)
    {
        foreach ($emails as $email) {
            if (!empty($email['related_booking_id'])) {
                // You would load the actual booking here
                return [
                    'id' => $email['related_booking_id'],
                    'crm_id' => 'CRM-' . $email['related_booking_id'] // Placeholder
                ];
            }
        }

        return null;
    }
}
