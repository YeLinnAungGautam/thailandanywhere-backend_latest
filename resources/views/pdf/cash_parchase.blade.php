<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking PDF</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .page {
            width: 100%;
            min-height: 100vh;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            margin: 0;
            padding: 10px 0;
            color: #333;
            font-size: 18px;
        }

        .image-container {
            text-align: center;
            width: 100%;
            height: auto;
        }

        .image-container img {
            max-width: 100%;
            max-height: 700px;
            width: auto;
            height: auto;
            border: 1px solid #ddd;
        }

        .page-number {
            position: fixed;
            bottom: 20px;
            right: 20px;
            font-size: 12px;
            color: #666;
        }

        .no-image {
            text-align: center;
            color: #999;
            padding: 50px;
            border: 1px dashed #ccc;
        }
    </style>
</head>

<body>

    @if (isset($imageData) && is_array($imageData))
        @foreach ($imageData as $index => $data)
            {{-- Main Image Page --}}
            @if (isset($data['image']) && $data['image'])
                <div class="page">
                    <div class="page-header">
                        <h2>Booking Image</h2>

                    </div>
                    <div class="image-container">
                        <img src="{{ $data['image'] }}" alt="Booking Image">
                    </div>
                    <div class="page-number">Page {{ $index + 1 }}</div>
                </div>
            @endif

            {{-- Booking Confirmation Letters --}}
            @if (isset($data['relatable']['booking_confirm_letter']) && is_array($data['relatable']['booking_confirm_letter']))
                @foreach ($data['relatable']['booking_confirm_letter'] as $letterIndex => $letter)
                    <div class="page">
                        <div class="page-header">
                            <h2>Booking Confirmation Letter {{ $letterIndex + 1 }}</h2>
                        </div>
                        <div class="image-container">
                            @if (isset($letter['file']) && $letter['file'])
                                <img src="{{ $letter['file'] }}" alt="Confirmation Letter {{ $letterIndex + 1 }}">
                            @else
                                <div class="no-image">
                                    <p>Confirmation letter image not available</p>
                                </div>
                            @endif
                        </div>
                        <div class="page-number">Page {{ $index + $letterIndex + 2 }}</div>
                    </div>
                @endforeach
            @endif

            {{-- Tax Receipts --}}
            @if (isset($data['relatable']['tax_credit']) && is_array($data['relatable']['tax_credit']))
                @foreach ($data['relatable']['tax_credit'] as $receiptIndex => $receipt)
                    <div class="page">
                        <div class="page-header">
                            <h2>Tax Receipt {{ $receiptIndex + 1 }}</h2>
                        </div>
                        <div class="image-container">
                            @if (isset($receipt['receipt_image']) && $receipt['receipt_image'])
                                <img src="{{ $receipt['receipt_image'] }}" alt="Tax Receipt {{ $receiptIndex + 1 }}">
                            @else
                                <div class="no-image">
                                    <p>Receipt image not available</p>
                                </div>
                            @endif
                        </div>
                        <div class="page-number">Page
                            {{ $index + count($data['relatable']['tax_credit'] ?? []) + $receiptIndex + 2 }}
                        </div>
                    </div>
                @endforeach
            @endif
        @endforeach
    @else
        <div class="page">
            <div class="page-header">
                <h2>No Data Available</h2>
            </div>
            <div class="no-image">
                <p>No booking data provided</p>
            </div>
        </div>
    @endif

</body>

</html>
