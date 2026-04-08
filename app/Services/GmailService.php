<?php
namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

class GmailService
{
    public GoogleClient $client;
    public Gmail $service;

    /** The Gmail inbox address this token belongs to (auto-detected or hardwired) */
    public string $accountEmail;

    public function __construct(?array $accessToken = null)
    {
        $client = new GoogleClient();

        $scopes = [
            Gmail::MAIL_GOOGLE_COM,
            'https://www.googleapis.com/auth/gmail.modify',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
        ];

        $inboxEmail = env('GMAIL_INBOX_EMAIL', 'negyi.partnership@thanywhere.com');

        // ── Priority 1: Service Account with Domain-Wide Delegation ────────────
        // No browser/OAuth flow needed. The service account impersonates the inbox
        // address server-side. Requires one-time Google Workspace admin setup.
        $serviceAccountPath = storage_path(
            'app/' . env('GMAIL_SERVICE_ACCOUNT_JSON', 'gmail-service-account.json')
        );

        if (file_exists($serviceAccountPath)) {
            $client->setAuthConfig($serviceAccountPath);
            $client->setSubject($inboxEmail);          // impersonate the inbox
            $client->addScope($scopes);
            $client->setAccessType('offline');
        } else {
            // ── Priority 2: OAuth token file (refresh_token stored on disk) ────
            $client->setClientId(config('services.google_gmail.client_id') ?? env('GOOGLE_GMAIL_CLIENT_ID'));
            $client->setClientSecret(config('services.google_gmail.client_secret') ?? env('GOOGLE_GMAIL_CLIENT_SECRET'));
            $client->setRedirectUri(config('services.google_gmail.redirect') ?? env('GOOGLE_GMAIL_REDIRECT_URL'));
            $client->setScopes($scopes);
            $client->setAccessType('offline');
            $client->setPrompt('consent');

            // Use injected token, or auto-load from file
            $tokenData = $accessToken ?? $this->loadTokenFromFile();

            if ($tokenData) {
                try {
                    $client->setAccessToken($tokenData);

                    if ($client->isAccessTokenExpired()) {
                        $refreshToken = $client->getRefreshToken()
                            ?? ($tokenData['refresh_token'] ?? null);

                        if ($refreshToken) {
                            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

                            if (isset($newToken['error'])) {
                                \Log::warning('Gmail token refresh failed', [
                                    'error'             => $newToken['error'],
                                    'error_description' => $newToken['error_description'] ?? '',
                                ]);
                            } else {
                                // Preserve refresh_token if omitted from response
                                if (!isset($newToken['refresh_token']) && isset($tokenData['refresh_token'])) {
                                    $newToken['refresh_token'] = $tokenData['refresh_token'];
                                }
                                $this->persistTokenToFile($newToken);
                            }
                        }
                    }
                } catch (\InvalidArgumentException $e) {
                    \Log::error('GmailService: invalid token, skipping auth. ' . $e->getMessage());
                }
            }
        }

        $this->client       = $client;
        $this->service      = new Gmail($client);
        $this->accountEmail = $inboxEmail;
    }

    /**
     * Load Gmail token from local storage file.
     */
    private function loadTokenFromFile(): ?array
    {
        if (\Storage::exists('gmail_token.json')) {
            $json = \Storage::get('gmail_token.json');
            return json_decode($json, true) ?: null;
        }

        return null;
    }

    /**
     * Persist a (refreshed) token back to the local storage file.
     */
    private function persistTokenToFile(array $token): void
    {
        \Storage::put('gmail_token.json', json_encode($token));
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

    /**
     * Recursively parse a Gmail message payload (multipart/mixed, alternative, etc.)
     * Extracts text/html, text/plain, and downloads attachments.
     */
    public function parseMessagePayload(array $payload, string $msgId): array
    {
        $html = '';
        $plain = '';
        $attachments = [];

        $this->extractPartsRecurse($payload['parts'] ?? [], $msgId, $html, $plain, $attachments);

        // If no parts (simple message), check payload body directly
        if (empty($payload['parts'])) {
            $data = $payload['body']['data'] ?? null;
            if ($data) {
                $decoded = base64_decode(strtr($data, '-_', '+/')) ?: '';
                $mime = $payload['mimeType'] ?? '';
                if ($mime === 'text/html') $html .= $decoded;
                else $plain .= $decoded;
            }
        }

        return [
            'body' => $html ?: $plain,
            'attachments' => $attachments
        ];
    }

    private function extractPartsRecurse(array $parts, string $msgId, string &$html, string &$plain, array &$attachments): void
    {
        foreach ($parts as $part) {
            $mime = $part['mimeType'] ?? '';

            // Handle nested parts (like multipart/mixed containing multipart/alternative)
            if (!empty($part['parts'])) {
                $this->extractPartsRecurse($part['parts'], $msgId, $html, $plain, $attachments);
                continue;
            }

            // Handle Attachments
            if (!empty($part['filename']) && !empty($part['body']['attachmentId'])) {
                try {
                    $attId = $part['body']['attachmentId'];
                    $attObj = $this->service->users_messages_attachments->get('me', $msgId, $attId);
                    $attData = base64_decode(strtr($attObj->getData(), '-_', '+/'));
                    
                    $ext = pathinfo($part['filename'], PATHINFO_EXTENSION);
                    $filename = \Str::slug(pathinfo($part['filename'], PATHINFO_FILENAME));
                    if ($ext) {
                        $filename .= '.' . $ext;
                    }
                                
                    $path = "emails/attachments/{$msgId}_{$filename}";
                    \Storage::disk('public')->put($path, $attData);

                    $attachments[] = [
                        'filename' => $part['filename'],
                        'mime_type' => $mime,
                        'size' => $part['body']['size'] ?? 0,
                        'path' => $path,
                        'url' => asset(\Storage::url($path)),
                    ];
                } catch (\Exception $e) {
                    \Log::error("Failed to download attachment {$part['filename']}: " . $e->getMessage());
                }
                continue;
            }

            // Output body
            $data = $part['body']['data'] ?? null;
            if ($data) {
                $decoded = base64_decode(strtr($data, '-_', '+/')) ?: '';
                if ($mime === 'text/html') {
                    $html .= $decoded;
                } elseif ($mime === 'text/plain') {
                    $plain .= $decoded;
                }
            }
        }
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
     *
     * @param int    $limit       Max number of messages to fetch
     * @param string|null $accountEmail The Gmail address that owns the token (used for sent/received classification)
     * @param string|null $extraQuery   Additional Gmail search query (e.g. "to:negyi.partnership@thanywhere.com OR from:negyi.partnership@thanywhere.com")
     */
    public function syncRecentEmails(int $limit = 50, ?string $accountEmail = null, ?string $extraQuery = null): int
    {
        $baseQuery = $extraQuery ?: 'in:inbox OR in:sent';

        $params = [
            'maxResults' => $limit,
            'q' => $baseQuery
        ];

        $messages = $this->listMessages($params);
        $syncedCount = 0;

        foreach ($messages as $messageData) {
            try {
                $this->saveEmailToDatabase($messageData, $accountEmail);
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
     *
     * @param array       $messageData  Decoded Gmail message array
     * @param string|null $accountEmail The Gmail address that owns the token (used for sent/received classification)
     */
    private function saveEmailToDatabase(array $messageData, ?string $accountEmail = null): void
    {
        $headers = $this->extractHeaders($messageData);
        $body = $this->extractBody($messageData);

        // Check if email already exists
        $existing = \App\Models\EmailLog::where('gmail_message_id', $messageData['id'])->first();
        if ($existing) {
            return; // Skip if already synced
        }

        $emailType = $this->determineEmailType($headers['from'] ?? '', $accountEmail);

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
     * Determine if email is sent or received based on sender.
     *
     * @param string      $fromHeader   The raw From: header value
     * @param string|null $accountEmail The Gmail address that owns the token.
     *                                  Falls back to config('mail.from.address') if null.
     */
    private function determineEmailType(string $fromHeader, ?string $accountEmail = null): string
    {
        $referenceEmail = $accountEmail ?? config('mail.from.address');
        $fromEmail = $this->extractEmailAddress($fromHeader);

        return strtolower($fromEmail) === strtolower($referenceEmail) ? 'sent' : 'received';
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
