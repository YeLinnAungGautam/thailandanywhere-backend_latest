<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1>Thread: {{ $threadId }}</h1>
    @foreach ($messages as $m)
        <div style="border:1px solid #eee;padding:8px;margin-bottom:8px;">
            <div><strong>From:</strong> {{ $m['from'] }}</div>
            <div><strong>Date:</strong> {{ $m['date'] }}</div>
            <div style="white-space:pre-wrap;margin-top:4px;">{!! nl2br(e($m['body'])) !!}</div>
        </div>
    @endforeach

    <h3>Reply</h3>
    <form action="{{ route('gmail.reply') }}" method="post">
        @csrf
        <input type="hidden" name="threadId" value="{{ $threadId }}">
        <input type="hidden" name="to" value="{{ $messages[0]['from'] ?? '' }}">
        <input type="hidden" name="inReplyTo" value="{{ $messages[0]['gmail_message_id'] ?? '' }}">
        <div><input name="subject" value="Re: {{ $messages[0]['subject'] ?? '' }}"></div>
        <div>
            <textarea name="body" rows="6"></textarea>
        </div>
        <div><button type="submit">Send Reply</button></div>
    </form>
</body>

</html>
