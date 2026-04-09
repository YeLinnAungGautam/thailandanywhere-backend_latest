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
        $queryParts = ["in:inbox OR in:sent OR to:{$inboxEmail} OR from:{$inboxEmail}"];

        if ($since) {
            // Gmail accepts after:YYYY/MM/DD
            $date         = \Carbon\Carbon::parse($since)->format('Y/m/d');
            $queryParts[] = "after:{$date}";
        }

        // $queryParts[] = 'is:unread'; // User requested to fetch both read and unread

        $query = implode(' ', $queryParts);

        $mode = $fetchAll ? 'ALL messages' : 'recent messages';
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
                    // Skip if already stored (do this early to avoid API calls and re-downloading attachments)
                    if (EmailTicketMessage::where('gmail_message_id', $msg->getId())->exists()) {
                        $skipped++;

                        continue;
                    }

                    $full     = $gmail->getMessage($msg->getId(), ['format' => 'full']);
                    $threadId = $full['threadId'] ?? $msg->getThreadId();

                    // Extract category from labelIds
                    $labels = $full['labelIds'] ?? [];
                    $category = 'Primary';
                    if (in_array('CATEGORY_PROMOTIONS', $labels)) {
                        $category = 'Promotions';
                    } elseif (in_array('CATEGORY_SOCIAL', $labels)) {
                        $category = 'Social';
                    } elseif (in_array('CATEGORY_UPDATES', $labels)) {
                        $category = 'Updates';
                    } elseif (in_array('CATEGORY_FORUMS', $labels)) {
                        $category = 'Forums';
                    }

                    // Extract headers
                    $headers = collect($full['payload']['headers'] ?? []);
                    $from    = $headers->firstWhere('name', 'From')['value'] ?? '';
                    $to      = $headers->firstWhere('name', 'To')['value'] ?? '';
                    $subject = $headers->firstWhere('name', 'Subject')['value'] ?? '(no subject)';

                    // Use recursive parser to handle deeply nested bodies and download attachments
                    $parsed = $gmail->parseMessagePayload($full['payload'] ?? [], $full['id']);
                    $body   = $parsed['body'];
                    $attachments = $parsed['attachments'];

                    // Determine if the message is incoming
                    $isIncoming = (stripos($from, $inboxEmail) === false);
                    $customerEmail = $isIncoming ? $from : $to;

                    // Upsert ticket (one per thread)
                    $ticket = EmailTicket::firstOrCreate(
                        ['gmail_thread_id' => $threadId],
                        [
                            'subject'        => mb_substr($subject, 0, 255),
                            'customer_email' => mb_substr($customerEmail, 0, 255),
                            'status'         => 'open',
                            'category'       => $category,
                        ]
                    );

                    // Keep category updated with the most recent email in thread
                    $ticket->update(['category' => $category]);

                    $date = $headers->firstWhere('name', 'Date')['value'] ?? null;
                    $gmailDatetime = $date
                        ? \Carbon\Carbon::parse($date)
                        : now();

                    EmailTicketMessage::create([
                        'ticket_id'        => $ticket->id,
                        'from'             => mb_substr($from, 0, 255),
                        'to'               => mb_substr($to, 0, 255) ?: 'me',
                        'body'             => mb_substr($body, 0, 65535),
                        'attachments'      => !empty($attachments) ? json_encode($attachments) : null,
                        'gmail_message_id' => $full['id'],
                        'gmail_datetime'   => $gmailDatetime,
                        'is_incoming'      => $isIncoming,
                    ]);

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
