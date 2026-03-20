<?php
namespace App\Console\Commands;

use App\Models\EmailTicket;
use App\Models\EmailTicketMessage;
use App\Services\GmailService;
use Illuminate\Console\Command;

class GmailSync extends Command
{
    protected $signature = 'gmail:sync
                            {max=50 : Max messages per run (ignored when --all is used)}
                            {--since= : Only fetch emails after this date, e.g. 2026-01-01}
                            {--all : Fetch all messages (not just unread), paginating through all results}';

    protected $description = 'Sync Gmail inbox for negyi.partnership@thanywhere.com into email_tickets';

    public function handle()
    {
        // GmailService auto-loads from storage/app/gmail_token.json
        try {
            $gmail = new GmailService();
        } catch (\Exception $e) {
            $this->error('Failed to initialize Gmail service: ' . $e->getMessage());

            return 1;
        }

        // Verify connection
        try {
            $profile = $gmail->service->users->getProfile('me');
            $this->info('Connected: ' . $profile->getEmailAddress());
        } catch (\Exception $e) {
            $this->error('Gmail not connected: ' . $e->getMessage());

            return 1;
        }

        $inboxEmail = $gmail->accountEmail;
        $fetchAll   = $this->option('all');
        $since      = $this->option('since');   // e.g. "2026-01-01"
        $max        = (int) $this->argument('max');

        // Build Gmail search query
        $queryParts = ["to:{$inboxEmail} OR from:{$inboxEmail}"];

        if ($since) {
            // Gmail accepts after:YYYY/MM/DD
            $date         = \Carbon\Carbon::parse($since)->format('Y/m/d');
            $queryParts[] = "after:{$date}";
        }

        if (!$fetchAll) {
            $queryParts[] = 'is:unread';
        }

        $query = implode(' ', $queryParts);

        $mode = $fetchAll ? 'ALL messages' : 'unread messages';
        $this->info("Syncing {$mode} for {$inboxEmail}" . ($since ? " since {$since}" : '') . ' ...');
        $this->info("Query: {$query}");

        $synced    = 0;
        $skipped   = 0;
        $failed    = 0;
        $pageToken = null;

        do {
            // Fetch one page of message IDs
            $params = ['q' => $query, 'maxResults' => 500];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            try {
                $response  = $gmail->service->users_messages->listUsersMessages('me', $params);
                $messages  = $response->getMessages() ?? [];
                $pageToken = $response->getNextPageToken(); // null = last page
            } catch (\Exception $e) {
                $this->error('Failed to list messages: ' . $e->getMessage());

                return 1;
            }

            $this->info('Processing ' . count($messages) . ' messages on this page...');

            foreach ($messages as $msg) {
                // Respect --max limit when NOT using --all
                if (!$fetchAll && ($synced + $skipped + $failed) >= $max) {
                    $pageToken = null; // stop pagination

                    break;
                }

                try {
                    $full     = $gmail->getMessage($msg->getId(), ['format' => 'full']);
                    $threadId = $full['threadId'] ?? $msg->getThreadId();

                    // Extract headers
                    $headers = collect($full['payload']['headers'] ?? []);
                    $from    = $headers->firstWhere('name', 'From')['value'] ?? '';
                    $to      = $headers->firstWhere('name', 'To')['value'] ?? '';
                    $subject = $headers->firstWhere('name', 'Subject')['value'] ?? '(no subject)';

                    // Decode body
                    $decode = fn(?string $d): string => $d
                        ? (base64_decode(strtr($d, '-_', '+/')) ?: '')
                        : '';

                    $body  = $decode($full['payload']['body']['data'] ?? null);
                    $html  = '';
                    $plain = '';

                    foreach ($full['payload']['parts'] ?? [] as $part) {
                        $mime = $part['mimeType'] ?? '';
                        $data = $part['body']['data'] ?? null;
                        if ($mime === 'text/html' && $data) {
                            $html = $decode($data);
                        } elseif ($mime === 'text/plain' && $data) {
                            $plain = $decode($data);
                        }
                    }

                    $body = $html ?: ($body ?: $plain);

                    // Skip if already stored
                    if (EmailTicketMessage::where('gmail_message_id', $full['id'])->exists()) {
                        $skipped++;

                        continue;
                    }

                    // Upsert ticket (one per thread)
                    $ticket = EmailTicket::firstOrCreate(
                        ['gmail_thread_id' => $threadId],
                        [
                            'subject'        => mb_substr($subject, 0, 255),
                            'customer_email' => mb_substr($from, 0, 255),
                            'status'         => 'open',
                        ]
                    );

                    EmailTicketMessage::create([
                        'ticket_id'        => $ticket->id,
                        'from'             => mb_substr($from, 0, 255),
                        'to'               => mb_substr($to, 0, 255) ?: 'me',
                        'body'             => mb_substr($body, 0, 65535),
                        'gmail_message_id' => $full['id'],
                        'gmail_datetime'   => now(),
                        'is_incoming'      => true,
                    ]);

                    // Mark as read so regular syncs don't re-process it
                    try {
                        $gmail->service->users_messages->modify(
                            'me',
                            $full['id'],
                            new \Google\Service\Gmail\ModifyMessageRequest(['removeLabelIds' => ['UNREAD']])
                        );
                    } catch (\Exception $e) {
                        // Non-fatal
                    }

                    $this->line("  ✓ {$subject}");
                    $synced++;

                } catch (\Exception $e) {
                    $this->error("  ✗ Failed [{$msg->getId()}]: " . $e->getMessage());
                    \Log::error("gmail:sync error [{$msg->getId()}]: " . $e->getMessage());
                    $failed++;
                }
            }

        } while ($pageToken); // keep going if there are more pages

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("Synced:  {$synced}");
        $this->info("Skipped: {$skipped} (already in DB)");
        $this->info("Failed:  {$failed}");
        $this->info('Gmail sync completed!');

        return 0;
    }
}
