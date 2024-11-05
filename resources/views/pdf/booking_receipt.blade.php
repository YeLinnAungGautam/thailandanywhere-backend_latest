<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>

    <style>
        @page {
            margin: 0;
            /* margin-top: 300px;
            padding: 10px 40px */
        }

        .page-break {
            page-break-after: always;
        }

        body {
            margin: 0px;
            font-family: 'Poppins', sans-serif;
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;

        }

        .header-title {
            font-size: 14px;
        }

        .header-desc {
            font-size: 12px;
            display: block;
            margin-bottom: 4px;
            font-weight: normal;
        }

        .header-heading {
            color: #ff5b00;
            font-size: 18px;
            font-weight: normal;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }


        .header-table th,
        .header-table td {
            /* border: 1px solid black; */
            text-align: left
        }

        .header-table th {
            font-weight: normal;
            font-size: 10px;
            color: #6f6f6f
        }

        .header-table td {
            font-weight: normal;
            font-size: 10px;
        }

        .body-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-bottom: 1px dashed black;
        }

        .body-table th,
        .body-table td {
            text-align: left
        }

        .body-table th {
            padding: 10px 5px;
            background: #ffe5d7;
            color: #ff5b00;
            font-size: 10px;
            font-weight: normal;
            font-family: sans-serif;
        }

        .body-table td {
            padding: 10px;
            font-size: 10px;
            font-family: sans-serif;
            font-weight: normal;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .footer-table tr {
            font-size: 10px;
            font-weight: normal;
        }

        /**
            * Define the width, height, margins and position of the watermark.
            **/
        #watermark {
            position: fixed;
            bottom: 0px;
            left: 0px;
            /** The width and height may change
                    according to the dimensions of your letterhead
                **/
            /** Your watermark should be behind every content**/
            z-index: -1000;
        }

        .break-before {
            page-break-before: always;
        }
    </style>
</head>

<body>
    <div id="watermark">
        <img src="{{ public_path() . '/assets/template.jpg' }}" height="100%" width="100%" />
    </div>
    <div>
        <div style="margin-top: 300px;
        padding: 10px 40px">
            <h3 class="header-heading">Invoice</h3>
            <table class="header-table">
                <tbody>
                    <tr>
                        <th style="width:70%">BILL TO</th>
                        <th>INVOICE</th>
                        <td>{{ $data->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td style="width:70%">{{ $data->customer->name }} / {{ $data->customer->phone_number }}</td>
                        <th>CRMID</th>
                        <td>{{ $data->crm_id }}</td>
                    </tr>
                    @if ($data->is_past_info)
                        <tr>
                            <td style="width:70%"></td>
                            <th>PAST CRMID</th>
                            <td>{{ $data->past_crm_id }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th style="width:70%">DATE</th>
                        <th>TERM</th>
                    </tr>
                    <tr>
                        <td style="width:70%">{{ $data->created_at->format('d-m-Y') }}</td>
                        <th>DUE DATE</th>
                        <td>{{ $data->balance_due_date }}</td>
                    </tr>
                </tbody>
            </table>
            <table class="body-table" style="max-height: 100px !important;">
                <tbody>
                    <tr>
                        <th>SERVICE DATE</th>
                        <th>SERVICE</th>
                        <th style="max-width:140px">DESCRIPTION</th>
                        <th>QTY</th>
                        <th>RATE</th>
                        <th>DISCOUNT</th>
                        <th>AMOUNT</th>
                    </tr>
                    @foreach ($data->items as $index => $row)
                        @if ($index == 4)
            </table>
            <div class="break-before"></div>
            <table class="body-table" style="max-height: 100px !important; margin-top:300px;">
                <tbody>
                    <tr>
                        <th>SERVICE DATE</th>
                        <th>SERVICE</th>
                        <th style="max-width:140px">DESCRIPTION</th>
                        <th>QTY</th>
                        <th>RATE</th>
                        <th>DISCOUNT</th>
                        <th>AMOUNT</th>
                    </tr>
                    @endif
                    @if ($index == 8)
            </table>
            <div class="break-before"></div>
            <table class="body-table" style="max-height: 100px !important; margin-top:300px;">
                <tbody>
                    <tr>
                        <th>SERVICE DATE</th>
                        <th>SERVICE</th>
                        <th style="max-width:140px">DESCRIPTION</th>
                        <th>QTY</th>
                        <th>RATE</th>
                        <th>DISCOUNT</th>
                        <th>AMOUNT</th>
                    </tr>
                    @endif
                    @if ($index == 16)
            </table>
            <div class="break-before"></div>
            <table class="body-table" style="max-height: 100px !important; margin-top:300px;">
                <tbody>
                    <tr>
                        <th>SERVICE DATE</th>
                        <th>SERVICE</th>
                        <th style="max-width:140px">DESCRIPTION</th>
                        <th>QTY</th>
                        <th>RATE</th>
                        <th>DISCOUNT</th>
                        <th>AMOUNT</th>
                    </tr>
                    @endif

                    <tr>
                        <td>{{ $row->service_date }}</td>
                        <td style="max-width: 100px;">{{ $row->product->name ?? '-' }} </br>
                            @if ($row->product_type === 'App\Models\Inclusive')
                                @if ($row->product->privateVanTours)
                                    @foreach ($row->product->privateVanTours as $pvt)
                                        <span style="font-size:7px;">{{ $pvt->product->name }}</span> </br>
                                    @endforeach
                                @endif

                                @if ($row->product->groupTours)
                                    @foreach ($row->product->groupTours as $gt)
                                        <span style="font-size:7px;">{{ $gt->product->name }}</span> </br>
                                    @endforeach
                                @endif

                                @if ($row->product->airportPickups)
                                    @foreach ($row->product->airportPickups as $ap)
                                        <span style="font-size:7px;">{{ $ap->product->name }}</span> </br>
                                    @endforeach
                                @endif

                                @if ($row->product->entranceTickets)
                                    @foreach ($row->product->entranceTickets as $et)
                                        <span style="font-size:7px;">{{ $et->name }}</span> </br>
                                    @endforeach
                                @endif

                                @if ($row->product->hotels)
                                    @foreach ($row->product->hotels as $et)
                                        <span style="font-size:7px;">{{ $et->product->name }}</span> </br>
                                    @endforeach
                                @endif
                            @endif
                        </td>
                        <td style="max-width: 120px">{{ $row->comment }}</td>
                        <td>{{ (int) $row->quantity * (int) ($row->days ? $row->days : 1) }}</td>
                        <td>{{ number_format((float) $row->selling_price) }}</td>
                        <td>{{ number_format((float) $row->discount) }}</td>
                        <td>{{ number_format($row->amount) }}</td>
                    </tr>
                    @if ($index == 4)
                        <tbody />
                    @endif
                    @endforeach
                </tbody>
            </table>
            <table class="footer-table">
                <tbody>
                    <tr>
                        <td>Thank you for booking with Thailand Anywhere. We are with you every step of the way.</td>
                        <td>SUB TOTAL</td>
                        <td style="font-size:14px;">
                            {{ number_format($data->sub_total) }} THB
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>DISCOUNT</td>
                        <td style="font-size:14px;">
                            {{ $data->discount }} THB
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>Total</td>
                        <td style="font-size:14px;">
                            {{ $data->sub_total - $data->discount }} THB
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>DEPOSIT</td>
                        <td style="font-size:14px;">
                            {{ $data->deposit }} THB
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>BALANCE DUE</td>
                        <td style="font-weight: bold; font-size:14px;">
                            {{ number_format($data->sub_total - $data->discount - $data->deposit) }} THB
                        </td>
                    </tr>
                    @if ($data->money_exchange_rate)
                        <tr>
                            <td></td>
                            <td>EXCHANGE RATE</td>
                            <td style="font-size:14px;">
                                {{ $data->money_exchange_rate }}
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>DEPOSIT IN {{ $data->payment_currency }}</td>
                            <td style="font-size:14px;">
                                {{ number_format($data->deposit * $data->money_exchange_rate) }}
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>BALANCE DUE ({{ $data->payment_currency }})</td>
                            <td style="font-weight: bold; font-size:14px;">
                                @if ($data->deposit === 0 || $data->deposit === 'null')
                                    @if ($data->payment_currency === 'USD')
                                        {{ number_format((float) ($data->sub_total - $data->discount) / ($data->money_exchange_rate ? (float) $data->money_exchange_rate : 1), '2', '.', '') }}
                                    @else
                                        {{ number_format((float) ($data->sub_total - $data->discount) * $data->money_exchange_rate ? (float) $data->money_exchange_rate : 1, '2', '.', '') }}
                                    @endif
                                @else
                                    @if ($data->payment_currency === 'USD')
                                        {{ number_format((float) ($data->sub_total - $data->discount - $data->deposit) / ($data->money_exchange_rate ? (float) $data->money_exchange_rate : 1), 2, '.', '') }}
                                    @else
                                        {{ number_format((float) ($data->sub_total - $data->discount - $data->deposit) * ($data->money_exchange_rate ? (float) $data->money_exchange_rate : 1), 2, '.', '') }}
                                    @endif
                                @endif
                                {{ $data->payment_currency }}
                            </td>
                        </tr>

                    @endif
                    <tr>
                        <td></td>
                        <td>PAYMENT STATUS</td>
                        @if ($data->payment_status === 'not_paid')
                            <td style="font-weight: bold; font-size:14px; color:red">
                                {{ ucwords(str_replace('_', ' ', $data->payment_status)) }}
                            </td>
                        @endif
                        @if ($data->payment_status === 'partially_paid')
                            <td style="font-weight: bold; font-size:14px; color:#ff5733">
                                {{ ucwords(str_replace('_', ' ', $data->payment_status)) }}
                            </td>
                        @endif
                        @if ($data->payment_status === 'fully_paid')
                            <td style="font-weight: bold; font-size:14px; color: green">
                                {{ ucwords(str_replace('_', ' ', $data->payment_status)) }}
                            </td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>


    </div>
</body>

</html>
