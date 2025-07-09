<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Accounting Report</title>
    <style>
        @page {
            margin: 0;
        }

        .page-break {
            page-break-after: always;
        }

        body {
            margin: 0;
            padding: 0;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .invoice-page {
            padding: 20px;
        }
    </style>
</head>

<body>
    @foreach ($data as $bookingData)
        @foreach ($bookingData['documents'] as $document)
            @if ($document['type'] === 'myanmar_invoice')
                <div class="invoice-page">
                    @include('pdf.booking_credit', ['booking' => $document['booking']])
                </div>
            @else
                <div>
                    <img src="{{ $document['path'] }}" style="width: 100%; height: 100vh">
                </div>
            @endif
            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach

        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>

</html>
