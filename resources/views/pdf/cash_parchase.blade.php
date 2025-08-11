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
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .page {
            width: 210mm;
            /* A4 width */
            height: 297mm;
            /* A4 height */
            page-break-after: always;
            position: relative;
            margin: 0;
            padding: 0;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        .image-container {
            width: 210mm;
            height: 280mm;
            /* Reserve space for index text */
            text-align: center;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .image-container img {
            width: 210mm;
            height: 280mm;
            display: block;
            margin: 0;
        }

        .page-index {
            position: absolute;
            bottom: 5mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
            background: white;
            padding: 2mm;
        }

        .no-image {
            width: 210mm;
            height: 280mm;
            text-align: center;
            background: #f5f5f5;
            color: #999;
            border: 1px dashed #ccc;
            padding-top: 140mm;
            /* Center vertically */

        }

        .no-image p {
            margin: 0;
            font-size: 16px;
        }

        /* For better compatibility with older PDF libraries */
        table.full-width-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        table.full-width-table td {
            padding: 0;
            margin: 0;
            vertical-align: top;
        }

        .image-cell {
            height: 280mm;
            text-align: center;
            vertical-align: middle;
        }

        .index-cell {
            height: 17mm;
            text-align: center;
            vertical-align: middle;
            background: white;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>
    @if (isset($imageData) && is_array($imageData))
        @php $pageNumber = 1; @endphp

        @foreach ($imageData as $index => $data)
            {{-- Main Booking Image Page --}}
            @if (isset($data['image']) && $data['image'])
                <div class="page">
                    <!-- Using table for better PDF compatibility -->
                    <table class="full-width-table">
                        <tr>
                            <td class="image-cell">
                                <img src="https://thanywhere.sgp1.cdn.digitaloceanspaces.com/images/1753459293_19866_6883aa5d5efff"
                                    alt="Booking Image" style="width: 210mm; height: 280mm;">
                            </td>
                        </tr>
                        <tr>
                            <td class="index-cell">
                                Booking Image - Page {{ $pageNumber }}
                            </td>
                        </tr>
                    </table>
                </div>
                @php $pageNumber++; @endphp
            @endif

            {{-- Booking Confirmation Letters --}}
            @if (isset($data['relatable']['booking_confirm_letter']) && is_array($data['relatable']['booking_confirm_letter']))
                @foreach ($data['relatable']['booking_confirm_letter'] as $letterIndex => $letter)
                    <div class="page">
                        <table class="full-width-table">
                            <tr>
                                <td class="image-cell">
                                    @if (isset($letter['file']) && $letter['file'])
                                        <img src="https://thanywhere.sgp1.cdn.digitaloceanspaces.com/images/68846527d2b7e_2025-07-26-11-48-31.png"
                                            alt="Confirmation Letter" style="width: 210mm; height: 280mm;">
                                    @else
                                        <div class="no-image">
                                            <p>Confirmation Letter {{ $letterIndex + 1 }}<br>Image not available</p>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="index-cell">
                                    Booking Confirmation Letter {{ $letterIndex + 1 }} - Page {{ $pageNumber }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    @php $pageNumber++; @endphp
                @endforeach
            @endif

            {{-- Tax Receipts --}}
            @if (isset($data['relatable']['tax_credit']) && is_array($data['relatable']['tax_credit']))
                @foreach ($data['relatable']['tax_credit'] as $receiptIndex => $receipt)
                    <div class="page">
                        <table class="full-width-table">
                            <tr>
                                <td class="image-cell">
                                    @if (isset($receipt['receipt_image']) && $receipt['receipt_image'])
                                        <img src="https://thanywhere.sgp1.cdn.digitaloceanspaces.com/images/1754732319_99311_6897171f88cc5"
                                            alt="Tax Receipt" style="width: 210mm; height: 280mm;">
                                    @else
                                        <div class="no-image">
                                            <p>Tax Receipt {{ $receiptIndex + 1 }}<br>Image not available</p>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="index-cell">
                                    Tax Receipt {{ $receiptIndex + 1 }} - Page {{ $pageNumber }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    @php $pageNumber++; @endphp
                @endforeach
            @endif
        @endforeach
    @else
        <div class="page">
            <table class="full-width-table">
                <tr>
                    <td class="image-cell">
                        <div class="no-image">
                            <p>No booking data provided</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="index-cell">
                        No Data - Page 1
                    </td>
                </tr>
            </table>
        </div>
    @endif
</body>

</html>
