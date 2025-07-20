<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Tax Invoice</title>

    <style>
        @page {
            margin: 0;
        }

        .page-break {
            page-break-after: always;
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            margin: 2 !important;
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

        .checkbox {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 10px;
            border: 1px solid #000;
        }

        /* .checked {
            background-image: url("{{ public_path() . '/assets/checked.png' }}");
        } */
    </style>
</head>

<body>
    @foreach ($booking->items->chunk(5) as $chunkIndex => $itemChunk)
        @if ($chunkIndex > 0)
            <div class="page-break"></div>
        @endif

        <table style="width: 100%;">
            <td style="width: 60%;">
                <div class="left">
                    <h2>TH ANYWHERE CO., LTD.</h2>
                    <p>TH Anywhere (Head Office)</p>
                    <p>143/50, Thepprasit Road, Chonburi</p>
                    <p><strong>Tax ID:</strong> 010555809643B</p>
                    <p><strong>Tel:</strong> 020042354</p>
                </div>
            </td>

            <td style="width: 40%">
                <div class="left">
                    <h2>TAX INVOICE / RECEIPT <img src="{{ public_path() . '/assets/46245.png' }}" height="20px"
                            width="20px" /></h2>
                    <p><strong>Date:</strong> {{ $booking->created_at->format('d M Y') }}</p>
                    <p><strong>Invoice No.:</strong> {{ $booking->crm_id }}</p>
                    <p><strong>Agency Sold by:</strong> TH Anywhere Myanmar</p>
                </div>
            </td>
        </table>

        <table style="width: 90%;">
            <td style="width: 50%;">
                <div class="left">
                    <h4>CUSTOMER DETAIL:</h4>
                    <p>{{ $booking->customer->name }}</p>
                    <p>{{ $booking->customer->phone_number ?? '+959 123 456 789' }}</p>
                    <p>{{ $booking->customer->country ?? 'Myanmar' }}</p>
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
                @foreach ($itemChunk as $item)
                    <tr class="row">
                        <td style="width: 160px; font-size: 12px">{{ $item->product->name ?? '-' }}</td>
                        <td style="font-size: 10px; padding: 10px 0; width: 220px">
                            {{ $item->comment ?? 'Premier Room with Breakfast' }}<br>
                            @if ($item->service_date)
                                Service Date: {{ $item->service_date }}<br>
                            @endif
                            @if ($item->days && $item->quantity && $item->selling_price)
                                {{ $item->days }} Nights x {{ $item->quantity }} Room x
                                {{ number_format($item->selling_price) }}
                            @endif
                        </td>
                        <td style="text-align: center">
                            {{ (int) $item->quantity * (int) ($item->days ? $item->days : 1) }}</td>
                        <td>{{ number_format((float) $item->selling_price) }} thb</td>
                        <td>{{ number_format($item->amount) }} thb</td>
                    </tr>
                @endforeach
            </tbody>
        </table>


        <table class="totals" style="width: 94%; margin-top: 10px">
            <tr>
                <td style="width: 82%;text-align: right; padding-bottom: 10px">Total</td>
                <td style="text-align: right; padding-bottom: 10px">
                    {{ number_format($booking->grand_total) }} thb</td>
            </tr>
            <tr>
                <td style="width: 82%;text-align: right; padding-bottom: 10px">Profit Share</td>
                <td style="text-align: right; padding-bottom: 10px">- {{ number_format($booking->commission ?? 0) }}
                    thb</td>
            </tr>
            <tr>
                <td style="width: 82%;text-align: right; padding-bottom: 10px">Subtotal</td>
                <td style="text-align: right; padding-bottom: 10px">
                    {{ number_format($booking->sub_total_with_vat ?? 0) }}
                    thb</td>
            </tr>
            <tr>
                <td style="width: 82%;text-align: right; padding-bottom: 10px">VAT 7%</td>
                <td style="text-align: right; padding-bottom: 10px">
                    {{ number_format($booking->vat ?? 0, 2) }} thb
                    thb</td>
            </tr>
            <tr>
                <td style="width: 82%;text-align: right; padding-bottom: 10px">Total Excluding VAT</td>
                <td style="text-align: right; padding-bottom: 10px">
                    {{ number_format($booking->total_excluding_vat ?? 0, 2) }}
                    thb</td>
            </tr>
            <tr>
                <td style="width: 82%;text-align: right; padding-bottom: 10px">Grand Total</td>
                <td style="text-align: right; padding-bottom: 10px">
                    {{ number_format($booking->sub_total_with_vat) }}
                    thb</td>
            </tr>
        </table>

        <div class="checkbox-group" style="margin-top: 100px">
            <div>
                <img src="{{ public_path() . '/assets/checked.png' }}" class="checkbox" height="10%" />
                <span style="margin-left: 0px;"></span> Bank
                <span class="checkbox {{ $booking->payment_method == 'Cash' ? 'checked' : '' }}"
                    style="margin-left: 10px"></span> Cash
                <span class="checkbox {{ $booking->payment_method == 'Credit' ? 'checked' : '' }}"
                    style="margin-left: 10px"></span> Credit
                <span class="checkbox {{ $booking->payment_method == 'Other' ? 'checked' : '' }}"
                    style="margin-left: 10px"></span> Other
            </div>
        </div>

        <div class="payment" style="margin-bottom: 40px">
            <h3>PAYMENT TO :</h3>
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
            </table>
        </div>

        <p style="font-size: 14px">
            Disclosure: Market price of all cross border sales are calculated fairly by distributing gross profits
            equally
            (50/50).
        </p>
    @endforeach

</body>

</html>
