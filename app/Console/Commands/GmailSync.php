<?php
namespace App\Console\Commands;

use App\Models\EmailTicket;
use App\Models\EmailTicketMessage;
use App\Services\GmailService;
use Illuminate\Console\Command;

class GmailSync extends Command
{
    protected $signature = 'gmail:sync {max=50}';
    protected $description = 'Sync Gmail inbox and store new messages as ticket messages';

    public function handle()
    {
        // Try to get token from cache first (new system)
        $token = \Cache::get('gmail_access_token');

        // Fallback to file system if cache doesn't have it (old system)
        if (!$token) {
            $tokenJson = \Storage::exists('gmail_token.json') ? \Storage::get('gmail_token.json') : null;
            if (!$tokenJson) {
                $this->error('No Gmail token found. Please authenticate first using: GET /admin/gmail/auth/url');
                $this->error('Follow the OAuth flow to get authenticated.');

                return 1;
            }
            $token = json_decode($tokenJson, true);
        }

        try {
            $gmail = new GmailService($token);
        } catch (\Exception $e) {
            $this->error('Failed to initialize Gmail service: ' . $e->getMessage());

            return 1;
        }

        $this->info('Starting Gmail sync...');

        try {
            // list unread
            $list = $gmail->listMessages(['maxResults' => (int)$this->argument('max'), 'q' => 'is:unread']);
            $this->info("Found " . count($list) . " unread messages to sync");
        } catch (\Exception $e) {
            $this->error('Failed to fetch messages: ' . $e->getMessage());

            return 1;
        }

        foreach ($list as $m) {
            try {
                $this->info("Processing message: {$m['id']}");

                $threadId = $m['threadId'];
                // fetch full message (array)
                $full = $gmail->getMessage($m['id'], ['format' => 'full']);

                // normalize headers
                $headers = collect($full['payload']['headers'] ?? []);
                $from = $headers->firstWhere('name', 'From')['value'] ?? '';
                $to = $headers->firstWhere('name', 'To')['value'] ?? '';
                $subject = $headers->firstWhere('name', 'Subject')['value'] ?? '';

                // extract body (prefer HTML, fallback to plain text)
                $decode = function (?string $data): string {
                    if (!$data) {
                        return '';
                    }

                    return base64_decode(strtr($data, '-_', '+/')) ?: '';
                };

                $body = '';
                $payload = $full['payload'] ?? [];

                // If top-level body has data
                if (!empty($payload['body']['data'])) {
                    $body = $decode($payload['body']['data']);
                }

                // Traverse parts for text/html or text/plain
                $parts = $payload['parts'] ?? [];
                if ($parts) {
                    $html = '';
                    $plain = '';
                    foreach ($parts as $part) {
                        $mime = $part['mimeType'] ?? '';
                        $data = $part['body']['data'] ?? null;
                        if ($mime === 'text/html' && $data) {
                            $html = $decode($data);
                        } elseif ($mime === 'text/plain' && $data) {
                            $plain = $decode($data);
                        }
                    }
                    $body = $html ?: ($body ?: $plain);
                }

                try {
                    $ticket = EmailTicket::firstOrCreate(
                        ['gmail_thread_id' => $threadId],
                        [
                            'subject' => strlen($subject) > 255 ? substr($subject, 0, 255) : $subject,
                            'customer_email' => strlen($from) > 255 ? substr($from, 0, 255) : $from
                        ]
                    );
                } catch (\Exception $e) {
                    $this->error("Failed to create ticket for thread {$threadId}: " . $e->getMessage());

                    continue;
                }

                // avoid duplicate by gmail message id
                if (!EmailTicketMessage::where('gmail_message_id', $full['id'])->exists()) {
                    try {
                        // Truncate body if too long to prevent database errors
                        $truncatedBody = strlen($body) > 65535 ? substr($body, 0, 65535) : $body;

                        EmailTicketMessage::create([
                            'ticket_id' => $ticket->id,
                            'from' => strlen($from) > 255 ? substr($from, 0, 255) : $from,
                            'to' => 'me',
                            'body' => $truncatedBody,
                            'gmail_message_id' => $full['id'],
                            'gmail_datetime' => now(),
                            'is_incoming' => true,
                        ]);

                        $this->info("Synced message: {$subject}");
                    } catch (\Exception $e) {
                        $this->error("Failed to save message {$full['id']}: " . $e->getMessage());
                        \Log::error("Gmail sync error for message {$full['id']}: " . $e->getMessage());

                        continue; // Skip this message and continue with next
                    }
                }

                // mark message as read (so we don't process again)
                try {
                    $gmail->service->users_messages->modify('me', $full['id'], new \Google\Service\Gmail\ModifyMessageRequest(['removeLabelIds' => ['UNREAD']]));
                    $this->info("Marked message as read: {$full['id']}");
                } catch (\Exception $e) {
                    $this->warn("Failed to remove UNREAD label from {$full['id']}: {$e->getMessage()}");
                    \Log::error("Failed to remove UNREAD label: {$e->getMessage()}");
                }

            } catch (\Exception $e) {
                $this->error("Failed to process message {$m['id']}: " . $e->getMessage());
                \Log::error("Gmail sync error for message {$m['id']}: " . $e->getMessage());

                continue;
            }
        }

        $this->info('Gmail sync completed successfully!');

        return 0;
    }
}
