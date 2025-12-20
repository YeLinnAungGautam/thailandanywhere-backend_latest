<?php
namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

class GmailService
{
    public GoogleClient $client;
    public Gmail $service;

    public function __construct(?array $accessToken = null)
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google_gmail.client_id') ?? env('GOOGLE_GMAIL_CLIENT_ID'));
        $client->setClientSecret(config('services.google_gmail.client_secret') ?? env('GOOGLE_GMAIL_CLIENT_SECRET'));
        $client->setRedirectUri(config('services.google_gmail.redirect') ?? env('GOOGLE_GMAIL_REDIRECT_URL'));
        $client->setScopes([
            Gmail::MAIL_GOOGLE_COM,
            'https://www.googleapis.com/auth/gmail.modify',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
        ]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        if ($accessToken) {
            $client->setAccessToken($accessToken);
            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                }
            }
        }

        $this->client = $client;
        $this->service = new Gmail($client);
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function fetchAccessTokenWithAuthCode(string $code): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($code);
    }

    public function listMessages(array $params = []): array
    {
        $result = [];
        $response = $this->service->users_messages->listUsersMessages('me', $params);

        foreach ($response->getMessages() ?? [] as $msg) {
            $result[] = $this->getMessage($msg->getId(), ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'To', 'Date']]);
        }

        return $result;
    }

    public function getMessage(string $messageId, array $optParams = ['format' => 'full']): array
    {
        $message = $this->service->users_messages->get('me', $messageId, $optParams);

        return json_decode(json_encode($message), true);
    }

    public function getThread(string $threadId): array
    {
        $thread = $this->service->users_threads->get('me', $threadId, ['format' => 'full']);

        return json_decode(json_encode($thread), true);
    }

    public function sendRawMessage(string $raw): Message
    {
        $msg = new Message();
        $msg->setRaw($raw);

        return $this->service->users_messages->send('me', $msg);
    }

    /**
     * Send an email through Gmail API
     */
    public function sendEmail(array $emailData): array
    {
        $to = $emailData['to'];
        $subject = $emailData['subject'];
        $body = $emailData['body'];
        $cc = $emailData['cc'] ?? [];
        $bcc = $emailData['bcc'] ?? [];
        $attachments = $emailData['attachments'] ?? [];

        $fromEmail = config('mail.from.address');
        $fromName = config('mail.from.name');
        $from = "{$fromName} <{$fromEmail}>";

        $raw = $this->buildEmailMessage($to, $from, $subject, $body, $cc, $bcc, $attachments);
        $response = $this->sendRawMessage($raw);

        return [
            'message_id' => $response->getId(),
            'thread_id' => $response->getThreadId(),
            'raw_response' => json_decode(json_encode($response), true)
        ];
    }

    /**
     * Send a reply to an existing email
     */
    public function sendReply(array $replyData): array
    {
        $to = $replyData['to'];
        $subject = $replyData['subject'];
        $body = $replyData['body'];
        $threadId = $replyData['thread_id'] ?? null;
        $inReplyTo = $replyData['in_reply_to'] ?? null;
        $attachments = $replyData['attachments'] ?? [];

        $fromEmail = config('mail.from.address');
        $fromName = config('mail.from.name');
        $from = "{$fromName} <{$fromEmail}>";

        // Get references from original thread if available
        $references = null;
        if ($threadId) {
            try {
                $thread = $this->getThread($threadId);
                if (!empty($thread['messages'])) {
                    $lastMessage = end($thread['messages']);
                    $headers = $lastMessage['payload']['headers'] ?? [];
                    foreach ($headers as $header) {
                        if ($header['name'] === 'Message-ID') {
                            $references = $header['value'];

                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue without references if thread lookup fails
            }
        }

        $raw = $this->buildEmailMessage($to, $from, $subject, $body, [], [], $attachments, $inReplyTo, $references, $threadId);
        $response = $this->sendRawMessage($raw);

        return [
            'message_id' => $response->getId(),
            'thread_id' => $response->getThreadId(),
            'raw_response' => json_decode(json_encode($response), true)
        ];
    }

    /**
     * Sync recent emails from Gmail to local database
     */
    public function syncRecentEmails(int $limit = 50): int
    {
        $params = [
            'maxResults' => $limit,
            'q' => 'in:inbox OR in:sent'
        ];

        $messages = $this->listMessages($params);
        $syncedCount = 0;

        foreach ($messages as $messageData) {
            try {
                $this->saveEmailToDatabase($messageData);
                $syncedCount++;
            } catch (\Exception $e) {
                \Log::error('Failed to sync email: ' . $e->getMessage(), [
                    'message_id' => $messageData['id'] ?? 'unknown'
                ]);
            }
        }

        return $syncedCount;
    }

    /**
     * Save Gmail message to local database
     */
    private function saveEmailToDatabase(array $messageData): void
    {
        $headers = $this->extractHeaders($messageData);
        $body = $this->extractBody($messageData);

        // Check if email already exists
        $existing = \App\Models\EmailLog::where('gmail_message_id', $messageData['id'])->first();
        if ($existing) {
            return; // Skip if already synced
        }

        $emailType = $this->determineEmailType($headers['from'] ?? '');

        \App\Models\EmailLog::create([
            'type' => $emailType,
            'from_email' => $this->extractEmailAddress($headers['from'] ?? ''),
            'from_name' => $this->extractDisplayName($headers['from'] ?? ''),
            'to_email' => $this->extractEmailAddress($headers['to'] ?? ''),
            'cc' => !empty($headers['cc']) ? json_encode($this->parseEmailList($headers['cc'])) : null,
            'subject' => $headers['subject'] ?? '',
            'body' => $body['html'] ?? $body['plain'] ?? '',
            'plain_body' => $body['plain'] ?? strip_tags($body['html'] ?? ''),
            'status' => 'delivered',
            'delivered_at' => now(),
            'gmail_message_id' => $messageData['id'],
            'gmail_thread_id' => $messageData['threadId'] ?? null,
            'in_reply_to' => $headers['in-reply-to'] ?? null,
            'references' => $headers['references'] ?? null,
        ]);
    }

    /**
     * Extract headers from Gmail message
     */
    private function extractHeaders(array $messageData): array
    {
        $headers = [];
        $headerList = $messageData['payload']['headers'] ?? [];

        foreach ($headerList as $header) {
            $headers[strtolower($header['name'])] = $header['value'];
        }

        return $headers;
    }

    /**
     * Extract body from Gmail message
     */
    private function extractBody(array $messageData): array
    {
        $body = ['plain' => '', 'html' => ''];

        $this->extractBodyRecursive($messageData['payload'] ?? [], $body);

        return $body;
    }

    /**
     * Recursively extract body content from message parts
     */
    private function extractBodyRecursive(array $part, array &$body): void
    {
        if (isset($part['body']['data'])) {
            $mimeType = $part['mimeType'] ?? '';
            $data = base64_decode(str_replace(['-', '_'], ['+', '/'], $part['body']['data']));

            if ($mimeType === 'text/plain') {
                $body['plain'] .= $data;
            } elseif ($mimeType === 'text/html') {
                $body['html'] .= $data;
            }
        }

        if (isset($part['parts'])) {
            foreach ($part['parts'] as $subPart) {
                $this->extractBodyRecursive($subPart, $body);
            }
        }
    }

    /**
     * Determine if email is sent or received based on sender
     */
    private function determineEmailType(string $fromHeader): string
    {
        $ourEmail = config('mail.from.address');
        $fromEmail = $this->extractEmailAddress($fromHeader);

        return strtolower($fromEmail) === strtolower($ourEmail) ? 'sent' : 'received';
    }

    /**
     * Extract email address from header
     */
    private function extractEmailAddress(string $emailHeader): string
    {
        if (preg_match('/<([^>]+)>/', $emailHeader, $matches)) {
            return $matches[1];
        }

        return trim($emailHeader);
    }

    /**
     * Extract display name from header
     */
    private function extractDisplayName(string $emailHeader): ?string
    {
        if (preg_match('/^(.+)\s*<[^>]+>$/', $emailHeader, $matches)) {
            return trim($matches[1], '"');
        }

        return null;
    }

    /**
     * Parse comma-separated email list
     */
    private function parseEmailList(string $emailList): array
    {
        $emails = explode(',', $emailList);

        return array_map(function ($email) {
            return $this->extractEmailAddress(trim($email));
        }, $emails);
    }

    /**
     * Build email message with optional attachments and threading
     */
    private function buildEmailMessage(
        string $to,
        string $from,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        array $attachments = [],
        ?string $inReplyTo = null,
        ?string $references = null,
        ?string $threadId = null
    ): string {
        $eol = "\r\n";
        $headers = [];
        $headers[] = "From: {$from}";
        $headers[] = "To: {$to}";

        if (!empty($cc)) {
            $headers[] = "Cc: " . implode(', ', $cc);
        }

        if (!empty($bcc)) {
            $headers[] = "Bcc: " . implode(', ', $bcc);
        }

        $headers[] = "Subject: {$subject}";
        $headers[] = "MIME-Version: 1.0";

        if ($inReplyTo) {
            $headers[] = "In-Reply-To: {$inReplyTo}";
        }

        if ($references) {
            $headers[] = "References: {$references}";
        }

        // Simple text email for now (can be enhanced for HTML + attachments later)
        $headers[] = "Content-Type: text/html; charset=utf-8";

        $rawMessageString = implode($eol, $headers) . $eol . $eol . $body;
        $raw = base64_encode($rawMessageString);

        // convert to base64url
        return str_replace(['+', '/', '='], ['-', '_', ''], $raw);
    }

    // Helper to create raw RFC822 message (base64url encoded) - DEPRECATED, use buildEmailMessage instead
    public static function buildRawMessage(string $to, string $from, string $subject, string $body, ?string $inReplyTo = null, ?string $references = null): string
    {
        $eol = "\r\n";
        $headers = [];
        $headers[] = "From: {$from}";
        $headers[] = "To: {$to}";
        $headers[] = "Subject: {$subject}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/plain; charset=utf-8";
        if ($inReplyTo) {
            $headers[] = "In-Reply-To: {$inReplyTo}";
        }
        if ($references) {
            $headers[] = "References: {$references}";
        }

        $rawMessageString = implode($eol, $headers) . $eol . $eol . $body;
        $raw = base64_encode($rawMessageString);

        // convert to base64url
        return str_replace(['+', '/', '='], ['-', '_', ''], $raw);
    }
}
