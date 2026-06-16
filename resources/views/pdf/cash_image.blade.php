<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment Receipts PDF</title>
    <style>
        @page {
            size: A4;
            margin: 18mm 22mm;
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #111;
            margin: 0;
            padding: 0;
        }

        .page {
            page-break-after: always;
        }

        .slip-page {
            text-align: center;
            page-break-after: always;
        }

        .combined-container:last-child .slip-page {
            page-break-after: avoid;
        }

        /* Header row: two columns side by side */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .header-table .right {
            text-align: right;
        }

        .label {
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 1px;
            margin-bottom: 4px;
            text-transform: uppercase;
            color: #555;
        }

        .val {
            font-size: 12px;
            line-height: 1.6;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        table.items thead tr {
            border-top: 2px solid #111;
            border-bottom: 2px solid #111;
        }

        table.items th {
            padding: 6px 5px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-align: left;
        }

        table.items th:nth-child(3),
        table.items th:nth-child(4),
        table.items th:nth-child(5) {
            text-align: right;
        }

        table.items td {
            padding: 7px 5px;
            font-size: 11px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        table.items td:nth-child(3),
        table.items td:nth-child(4),
        table.items td:nth-child(5) {
            text-align: right;
        }

        /* Totals: table with two columns, right aligned */
        .totals-block {
            margin-top: 8px;
            border-top: 2px solid #111;
            padding-top: 10px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            text-align: right;
            font-size: 12px;
            padding: 2px 0;
        }

        .totals-table td.totals-label {
            padding-right: 40px;
            width: 70%;
        }

        .totals-table tr.grand td {
            font-weight: 700;
            font-size: 14px;
            padding-top: 4px;
        }

        .slip-img {
            max-width: 90%;
            height: 800px;
            width: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    @if (isset($imageData) && is_array($imageData))
        @foreach ($imageData as $url)
            @php
                $invoice = $url['invoice'] ?? null;
                $booking = $url['booking'] ?? null;
                $customer = $booking['customer'] ?? null;
                $invoice_number = $booking['invoice_generate'] ?? null;

                $currency = $invoice['currency'] ?? ($url['currency'] ?? 'THB');
                $cashAmount = (float) ($url['cash_amount'] ?? 0);

                $subTotal = $invoice['sub_total'] ?? $cashAmount / 1.07;
                $vatAmount = $invoice['vat'] ?? $cashAmount - $subTotal;
                $items = $invoice['items'] ?? [];

                $customerName = $customer['name'] ?? ($url['sender'] ?? '—');
                $customerPhone = $customer['phone_number'] ?? '—';
                $customerEmail = $customer['email'] ?? null;

                $imageUrl = $url['image']
                    ? 'https://thanywhere.sgp1.cdn.digitaloceanspaces.com/images/' . $url['image']
                    : null;
            @endphp

            <div class="combined-container">
                <!-- PAGE 1: INVOICE -->
                <div class="page">
                    <table class="header-table">
                        <tr>
                            <td style="width: 60%;">
                                <div style="font-size: 20px; font-weight: 900; margin-bottom: 5px;">TH ANYWHERE CO., LTD.
                                </div>
                                <div style="font-size: 11px; line-height: 1.9;">
                                    TH Anywhere (Head Office)<br>
                                    100, 151 Huay Kaew Rd, Chiang Mai<br>
                                    <strong>Tax ID:</strong> 0105555809643B<br>
                                    <strong>Tel:</strong> 020042354
                                </div>
                            </td>
                            <td class="right" style="width: 40%;">
                                <div style="font-size: 18px; font-weight: 900; margin-bottom: 8px;">PAYMENT</div>
                                <div style="font-size: 11px; line-height: 1.9;">
                                    <strong>Date:</strong> {{ $url['cash_image_date'] ?? '—' }}<br>
                                    <strong>Invoice No.:</strong> {{ $invoice_number ?? '—' }}<br>
                                    <strong>Agency Sold by:</strong> TH Anywhere Myanmar
                                </div>
                            </td>
                        </tr>
                    </table>

                    <table class="header-table">
                        <tr>
                            <td style="width: 50%;">
                                <div class="label">Customer Detail</div>
                                <div class="val">
                                    <strong>{{ $customerName }}</strong><br>
                                    {{ $customerPhone }}
                                    @if ($customerEmail)
                                        <br>{{ $customerEmail }}
                                    @endif
                                </div>
                            </td>
                            <td class="right" style="width: 50%;">
                                <div class="label">Agency Sold From</div>
                                <div class="val">
                                    TH Anywhere Co., Ltd.<br>
                                    Alan Pya Phaya Lan, Yangon<br>
                                    Myanmar
                                </div>
                            </td>
                        </tr>
                    </table>

                    <table class="items">
                        <thead>
                            <tr>
                                <th>PRODUCT</th>
                                <th>DESCRIPTION</th>
                                <th>QUANTITY</th>
                                <th>UNIT PRICE</th>
                                <th>TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>{{ $item['product_name'] }}</td>
                                    <td>-</td>
                                    <td style="text-align:right">{{ $item['quantity'] }}</td>
                                    <td style="text-align:right">{{ number_format($item['unit_price']) }}
                                        {{ $currency }}</td>
                                    <td style="text-align:right">{{ number_format($item['total_ex_vat']) }}
                                        {{ $currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding:12px 5px;color:#888">No items</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="totals-block">
                        <table class="totals-table">
                            <tr>
                                <td class="totals-label">Sub Total</td>
                                <td>{{ number_format($subTotal) }} {{ $currency }}</td>
                            </tr>
                            <tr>
                                <td class="totals-label">VAT 7%</td>
                                <td>{{ number_format($vatAmount) }} {{ $currency }}</td>
                            </tr>
                            <tr class="grand">
                                <td class="totals-label">Total Amount</td>
                                <td>{{ number_format($cashAmount) }} {{ $currency }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- PAGE 2: CASH SLIP -->
                <div class="slip-page">
                    @if ($imageUrl)
                        <img class="slip-img" src="{{ $imageUrl }}"
                            alt="Cash Image {{ $url['cash_image_id'] ?? '' }}">
                    @else
                        <p style="color:#888;font-size:13px;">No slip image available.</p>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</body>

</html>
