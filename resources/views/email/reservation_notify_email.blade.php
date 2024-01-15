<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Notify Email</title>

    <style>
        .font-semibold {
            font-weight: 600;
        }

        .italic {
            font-style: italic;
        }

        .space-y-1 {
            margin-top: 12px;
            margin-bottom: 0px
        }
    </style>

</head>

<body>
    {{-- <div id="watermark">
        <img src="{{ asset('assets/print.png') }}" height="100%" width="100%" />
    </div> --}}

    {!! $mail_body !!}

    {{-- <div id="watermark">
        <img src="{{ asset('assets/printf.png') }}" height="100%" width="100%" />
    </div> --}}
</body>

</html>
