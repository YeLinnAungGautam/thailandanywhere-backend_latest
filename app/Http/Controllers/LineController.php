<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class LineController extends Controller
{
    protected $bot;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken(config('line.channel_access_token'));

        $this->bot = new MessagingApiApi(
            new Client(),
            $config
        );
    }

    public function webhook(Request $request)
    {
        $body   = $request->getContent();
        $events = json_decode($body, true)['events'] ?? [];

        foreach ($events as $event) {
            $userId  = $event['source']['userId']  ?? null;
            $groupId = $event['source']['groupId'] ?? null;

            // \Log::info('=== LINE WEBHOOK ===');
            // \Log::info('User ID:  ' . $userId);
            // \Log::info('Group ID: ' . $groupId);
            // \Log::info('Event Type: ' . $event['type']);
        }

        return response()->json(['status' => 'ok', 'user_id' => $userId, 'group_id' => $groupId, 'event_id' => $event['type']]);
    }

    public function sendMessage(Request $request)
    {
        $message = $request->query('message', 'Hello from Laravel!');

        $textMessage = new TextMessage([
            'type' => 'text',
            'text' => $message,
        ]);

        $pushRequest = new PushMessageRequest([
            'to'       => 'C1e3dfc368c431d3fc32fd1aa400bd8e3',
            'messages' => [$textMessage],
        ]);

        $this->bot->pushMessage($pushRequest);

        return response()->json(['status' => 'success']);
    }
}
