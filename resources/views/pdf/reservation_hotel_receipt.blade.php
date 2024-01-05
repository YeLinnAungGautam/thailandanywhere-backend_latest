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
            color: #ff5b00
        }

        .header-table td {
            font-weight: normal;
            font-size: 10px;
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
        <img src="{{ public_path() . '/assets/hotel_template.jpg' }}" height="100%" width="100%" />
    </div>

    <div>
        <div style="margin-top: 430px;
        padding: 0px 23px">
            <table class="header-table">
                <tbody>
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Booking
                            Detail</th>
                        <th></th>
                        <th></th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Booking ID:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->crm_id }}</td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Reservation ID:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ preg_match('/_(\d+)$/', $data->crm_id, $matches) ? $matches[1] : '' }}</td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Reservation Code:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->slip_code ?? '-' }}</td>
                    </tr>
                    {{--  --}}
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Customer
                            Detail</th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Customer Name:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->booking->customer->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Passport No:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->booking->customer->nrc_number ? $data->booking->customer->nrc_number : '-' }}</td>
                    </tr>
                    {{--  --}}
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Ticket
                            Detail</th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Hotel Name:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->product->name ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Room Type:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->room->name ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Quantity Of Rooms:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->quantity }}
                        </td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Check-In:
                        </td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ Carbon\Carbon::parse($data->checkin_date)->format('d F Y') }}
                        </td>
                    </tr>
                    <td style="width:30%;font-size:13px;padding-bottom:12px!important">Check-Out:
                    </td>
                    <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                        {{ Carbon\Carbon::parse($data->checkout_date)->format('d F Y') }}

                    </td>
                    </tr>
                    {{-- Service Date --}}
                    {{-- <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Service Date:
                        </td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">{{Carbon\Carbon::parse($data->service_date)->format('d F Y')}}
                        </td>
                    </tr> --}}

                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">No. of Guests:
                        </td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            {{ $data->total_guest }}
                        </td>
                    </tr>

                    {{--  --}}
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Agent Detail
                        </th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Agent Name:
                        </td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                            @if (Str::startsWith($data->crm_id, 'CK'))
                                ChawKalayar
                            @elseif (Str::startsWith($data->crm_id, 'SH'))
                                Sunshine
                            @elseif (Str::startsWith($data->crm_id, 'HN'))
                                Hinn
                            @elseif (Str::startsWith($data->crm_id, 'CS'))
                                Chit Su
                            @elseif (Str::startsWith($data->crm_id, 'KN'))
                                Ko Nay Myo
                            @elseif (Str::startsWith($data->crm_id, 'EM'))
                                Ei Myat
                            @elseif (Str::startsWith($data->crm_id, 'SA'))
                                Admin
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th style="width:30%;font-size:13px;font-weight:bold;padding-bottom:12px!important">Payment
                            Status:</th>
                        <td
                            style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important;
    @if ($data->payment_status === 'fully_paid') color: green; @endif
    @if ($data->payment_status === 'partially_paid') color:#ff5733; @endif
    @if ($data->payment_status === 'not_paid') color:red; @endif">
                            {{ ucwords(str_replace('_', ' ', $data->payment_status)) }}
                        </td>

                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
