<?php
namespace App\Http\Controllers;

use App\Models\EmailTicket;
use App\Models\EmailTicketMessage;
use App\Models\Ticket;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\info;

class GmailController extends Controller
{
    public function redirectToGoogle()
    {
        $gmail = new GmailService();

        return redirect($gmail->getAuthUrl());
    }

    public function handleGoogleCallback(Request $request)
    {
        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('gmail.inbox')->with('error', 'No code returned');
        }

        $gmail = new GmailService();
        $token = $gmail->fetchAccessTokenWithAuthCode($code);

        // persist token somewhere (DB). For demo we store in cache/file (but prefer DB)
        // Save $token['access_token'], $token['refresh_token'], $token['expires_in']
        // Example: save to storage/app/gmail_token.json
        info('Gmail token', [
            'token' => json_encode($token),
        ]);
        Storage::put('gmail_token.json', json_encode($token));

        return redirect()->route('gmail.inbox')->with('success', 'Google connected');
    }

    protected function loadToken()
    {
        if (!\Storage::exists('gmail_token.json')) {
            return null;
        }

        return json_decode(\Storage::get('gmail_token.json'), true);
    }

    public function inbox()
    {
        $token = $this->loadToken();
        if (!$token) {
            return redirect()->route('google.connect');
        }

        $gmail = new GmailService($token);
        // list messages (get latest 25)
        $messages = $gmail->listMessages(['maxResults' => 25, 'labelIds' => ['INBOX']]);

        // normalize results for view
        $list = [];
        foreach ($messages as $m) {
            // $m is stdClass of message resource; extract headers
            $headers = collect($m->payload->headers ?? []);
            $subject = $headers->firstWhere('name', 'Subject')->value ?? '(no subject)';
            $from = $headers->firstWhere('name', 'From')->value ?? '';
            $date = $headers->firstWhere('name', 'Date')->value ?? '';

            $list[] = [
                'id' => $m['id'],
                'threadId' => $m['threadId'],
                'subject' => $subject,
                'from' => $from,
                'date' => $date,
            ];
        }

        return view('gmail.inbox', ['messages' => $list]);
    }

    public function thread($threadId)
    {
        $token = $this->loadToken();
        if (!$token) {
            return redirect()->route('google.connect');
        }

        $gmail = new GmailService($token);
        $thread = $gmail->getThread($threadId);

        // parse thread messages
        $parts = $thread->messages ?? [];
        $messages = [];
        foreach ($parts as $m) {
            $headers = collect($m->payload->headers ?? []);
            $subject = $headers->firstWhere('name', 'Subject')->value ?? '';
            $from = $headers->firstWhere('name', 'From')->value ?? '';
            $to = $headers->firstWhere('name', 'To')->value ?? '';
            $date = $headers->firstWhere('name', 'Date')->value ?? '';
            // get body
            $body = $this->getMessageBody($m);

            $messages[] = [
                'id' => $m->id,
                'gmail_message_id' => $m->id,
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'date' => $date,
                'body' => $body,
            ];
        }

        return view('gmail.thread', ['thread' => $thread, 'messages' => $messages, 'threadId' => $threadId]);
    }

    public function getMessageBody($message)
    {
        // message->payload may be multipart or singlepart
        if (isset($message->payload->parts) && is_array($message->payload->parts)) {
            foreach ($message->payload->parts as $part) {
                if ($part->mimeType === 'text/plain' && isset($part->body->data)) {
                    return $this->decodeBody($part->body->data);
                }
            }
            // fallback to first part body
            $first = $message->payload->parts[0] ?? null;
            if ($first && isset($first->body->data)) {
                return $this->decodeBody($first->body->data);
            }
        } else {
            if (isset($message->payload->body->data)) {
                return $this->decodeBody($message->payload->body->data);
            }
        }

        return '';
    }

    protected function decodeBody($data)
    {
        $raw = str_replace(['-', '_'], ['+', '/'], $data);
        $pad = strlen($raw) % 4;
        if ($pad) {
            $raw .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($raw);
    }

    public function reply(Request $request)
    {
        $request->validate([
            'threadId' => 'required|string',
            'to' => 'required|email',
            'subject' => 'required|string',
            'body' => 'required|string',
            'inReplyTo' => 'nullable|string',
        ]);

        $token = $this->loadToken();
        if (!$token) {
            return redirect()->route('google.connect');
        }

        $gmail = new GmailService($token);

        // build raw message, include In-Reply-To and References to keep threading
        $from = $request->user()?->email ?? 'me'; // 'me' used by API; but From header better be real email
        $raw = GmailService::buildRawMessage(
            $request->to,
            $from,
            $request->subject,
            $request->body,
            $request->input('inReplyTo'),
            $request->input('inReplyTo')
        );

        $message = $gmail->sendRawMessage($raw);

        // optional: persist to DB (create ticket if not exists)
        $threadId = $request->threadId;
        $ticket = EmailTicket::firstOrCreate(
            ['gmail_thread_id' => $threadId],
            ['subject' => $request->subject, 'customer_email' => $request->to]
        );

        EmailTicketMessage::create([
            'ticket_id' => $ticket->id,
            'from' => $from,
            'to' => $request->to,
            'body' => $request->body,
            'gmail_message_id' => $message->getId(),
            'gmail_datetime' => now(),
            'is_incoming' => false,
        ]);

        return back()->with('success', 'Reply sent');
    }
}
