<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Images and Invoices PDF</title>

    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            margin: 0 !important;
            padding: 10px 14px !important;
            width: 100%;
        }

        p {
            margin: 0px !important;
            padding: 2px 0px !important;
        }

        h2 {
            margin: 0px !important;
            padding: 4px 0px !important;
        }

        h4 {
            margin: 10px 0 0 0 !important;
            padding: 4px 0px !important;
        }

        .table {
            width: 97%;
            padding-top: 30px;
        }

        .table th {
            text-align: left;
            padding-bottom: 10px
        }

        .row td {
            text-align: left;
            padding-bottom: 10px
        }

        .totals tr {
            padding-bottom: 20px;
        }

        /*
         * This CSS is crucial for ensuring each combined image/invoice
         * block is on its own page.
         */
        .combined-container {
            page-break-after: always;
        }

        /*
         * We don't want a page break after the very last item.
         */
        .combined-container:last-child {
            page-break-after: avoid;
        }

        .image-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .checkbox {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 10px;
            border: 1px solid #000;
        }

        /* The original code had a checked image, which won't work without a public path.
           For this example, we'll just style the box. */
        .checkbox.checked {
            background-color: #000;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>

<body>
    @if (isset($imageData) && is_array($imageData))
        @foreach ($imageData as $url)
            <div class="combined-container">
                <!-- First Invoice Content (Summary) Starts Here -->
                @if (isset($url['booking']) && !empty($url['booking']))
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 60%;">
                                <div class="left">
                                    <h2>TH ANYWHERE CO., LTD.</h2>
                                    <p>TH Anywhere (Head Office)</p>
                                    <p>No.(39) ,Room (101), Alan Pya Pagoda Road,
                                        Dagon Township, Yangon , Myanmar</p>
                                    <p><strong>Tax ID:</strong> 136660861</p>
                                    <p><strong>Tel:</strong> 09421073987</p>
                                </div>
                            </td>

                            <td style="width: 40%">
                                <div class="left">
                                    <h2>TAX INVOICE / RECEIPT </h2>
                                    <p><strong>Date:</strong>
                                        @if (isset($url['booking']['created_at']))
                                            {{ date('d M Y', strtotime($url['booking']['created_at'])) }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                    <p><strong>Invoice No.:</strong> {{ $url['booking']['invoice_generate'] ?? 'N/A' }}
                                    </p>
                                    <p><strong>Exchange Rate:</strong> 130</p>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <table style="width: 90%;">
                        <tr>
                            <td style="width: 50%;">
                                <div class="left">
                                    <h4>CUSTOMER DETAIL:</h4>
                                    @if (isset($url['booking']['customer']))
                                        <p>{{ $url['booking']['customer']['name'] ?? 'N/A' }}</p>
                                        <p>{{ $url['booking']['customer']['phone_number'] ?? '+959 123 456 789' }}</p>
                                    @else
                                        <p>Customer data not available</p>
                                    @endif
                                    <p>Myanmar</p>
                                </div>
                            </td>

                            <td style="width: 50%">
                                <div style="text-align: right">
                                    <h4>AGENCY SOLD FROM:</h4>
                                    <p>TH Anywhere Co., Ltd.</p>
                                    <p>Alan Pya Phaya Lan, Yangon</p>
                                    <p>Myanmar</p>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <table class="table" style="min-height: 340px !important; border-bottom: 2px solid #000">
                        <thead style=" border-bottom: 2px solid #000">
                            <tr>
                                <th>PRODUCT</th>
                                <th>DESCRIPTION</th>
                                <th>QUANTITY</th>
                                <th>UNIT PRICE</th>
                                <th>TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $grandTotalMMK = 0; @endphp
                            @if (isset($url['booking']['grouped_items']) && count($url['booking']['grouped_items']) > 0)
                                @foreach ($url['booking']['grouped_items'] as $item)
                                    @php
                                        $amount = $item['amount'] ?? 0;
                                        $costPrice = $item['total_cost_price'] ?? 0;
                                        $quantity = $item['quantity'] ?? 1;
                                        $unitPrice = (($amount - $costPrice) / 2 / $quantity) * 130;
                                        $totalPrice = (($amount - $costPrice) / 2) * 130;
                                        $grandTotalMMK += $totalPrice; // accumulate
                                    @endphp
                                    <tr class="row">
                                        <td style="width: 160px; font-size: 12px; padding: 10px 0">
                                            {{ ($item['product_name'] ?? 'General') . ' Commission' }}
                                        </td>
                                        <td style="font-size: 12px; padding: 10px 0; width: 220px">-</td>
                                        <td style="text-align: center">{{ $quantity }}</td>
                                        <td>{{ number_format($unitPrice) }} MMK</td>
                                        <td>{{ number_format($totalPrice) }} MMK</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>

                    <table class="totals" style="width: 94%; margin-top: 10px">

                        {{-- <tr>
                            <td style="width: 82%;text-align: right; padding-bottom: 10px">Profit Share</td>
                            <td style="text-align: right; padding-bottom: 10px">
                                - {{ number_format($url['booking']['commission'] ?? 0) }} THB
                            </td>
                        </tr> --}}
                        <tr>
                            <td style="width: 82%;text-align: right; padding-bottom: 10px">Subtotal</td>
                            <td style="text-align: right; padding-bottom: 10px">
                                <strong>{{ number_format($grandTotalMMK) }} MMK</strong>
                            </td>
                        </tr>
                        {{-- <tr>
                            <td style="width: 82%;text-align: right; padding-bottom: 10px">VAT 7%</td>
                            <td style="text-align: right; padding-bottom: 10px">
                                {{ number_format($url['booking']['vat'] ?? 0) }}
                                thb</td>
                        </tr> --}}
                        {{-- <tr>
                            <td style="width: 82%;text-align: right; padding-bottom: 10px">Total Excluding VAT</td>
                            <td style="text-align: right; padding-bottom: 10px">
                                {{ number_format($url['booking']['total_excluding_vat'] ?? 0, 2) }}
                                thb</td>
                        </tr> --}}
                        <tr>
                            <td style="width: 82%;text-align: right; padding-bottom: 10px">Grand Total</td>
                            <td style="text-align: right; padding-bottom: 10px">
                                <strong>{{ number_format($grandTotalMMK) }} MMK</strong>
                            </td>
                        </tr>
                    </table>

                    <div class="checkbox-group" style="margin-top: 100px">
                        <div>
                            <img src="{{ public_path() . '/assets/checked.png' }}" class="checkbox"
                                style="min-height: 10px" />
                            <span style="margin-left: 0px;"></span> Cash
                            <span class="checkbox " style="margin-left: 10px"></span> Bank
                            <span class="checkbox " style="margin-left: 10px"></span> Credit
                            <span class="checkbox " style="margin-left: 10px"></span> Other
                        </div>
                    </div>

                    <div class="payment" style="margin-bottom: 40px">
                        {{-- <h3>PAYMENT TO :</h3>
                        <table>
                            <tr>
                                <td style="width: 100px">Kasikorn Bank</td>
                            </tr>
                            <tr>
                                <td style="width: 100px">Account No</td>
                                <td style="width: 100px; text-align:center">:</td>
                                <td>198-1-06668-1</td>
                            </tr>
                            <tr>
                                <td style="width: 100px">Account Name:</td>
                                <td style="width: 100px; text-align:center">:</td>
                                <td>TH ANYWHERE CO.,LTD.</td>
                            </tr>
                        </table> --}}
                    </div>

                    <p style="font-size: 14px; margin-top: 40px">
                        Disclosure: Market price of all cross border sales are calculated fairly by distributing gross
                        profits
                        equally
                        (50/50)
                        .
                    </p>
                @endif
            </div>
        @endforeach
    @endif
</body>

</html>
