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
        $tokenJson = \Storage::exists('gmail_token.json') ? \Storage::get('gmail_token.json') : null;
        if (!$tokenJson) {
            $this->error('No token found. Connect first.');

            return 1;
        }
        $token = json_decode($tokenJson, true);
        $gmail = new GmailService($token);
        $gmail = new GmailService($token);

        // list unread
        $list = $gmail->listMessages(['maxResults' => (int)$this->argument('max'), 'q' => 'is:unread']);

        foreach ($list as $m) {
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

            $ticket = EmailTicket::firstOrCreate(
                ['gmail_thread_id' => $threadId],
                ['subject' => $subject, 'customer_email' => $from]
            );

            // avoid duplicate by gmail message id
            if (!EmailTicketMessage::where('gmail_message_id', $full['id'])->exists()) {
                EmailTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'from' => $from,
                    'to' => 'me',
                    'body' => $body,
                    'gmail_message_id' => $full['id'],
                    'gmail_datetime' => now(),
                    'is_incoming' => true,
                ]);
            }

            // mark message as read (so we don't process again)
            try {
                $gmail->service->users_messages->modify('me', $full['id'], new \Google\Service\Gmail\ModifyMessageRequest(['removeLabelIds' => ['UNREAD']]));
            } catch (\Exception $e) {
                \Log::error("Failed to remove UNREAD label: {$e->getMessage()}");
            }
        }
        $this->info('Sync complete');

        return 0;
    }
}
