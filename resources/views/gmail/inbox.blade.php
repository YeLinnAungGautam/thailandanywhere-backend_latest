<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1>Inbox</h1>
    @if (session('success'))
        <div>{{ session('success') }}</div>
    @endif
    <table>
        <thead>
            <tr>
                <th>From</th>
                <th>Subject</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($messages as $m)
                <tr>
                    <td>{{ $m['from'] }}</td>
                    <td><a href="{{ route('gmail.thread', $m['threadId']) }}">{{ $m['subject'] }}</a></td>
                    <td>{{ $m['date'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
