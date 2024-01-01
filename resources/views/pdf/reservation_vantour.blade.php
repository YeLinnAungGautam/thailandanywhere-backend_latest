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
    <img src="{{ public_path() . '/assets/vantour_template.jpg' }}" height="100%" width="100%" />
    </div>
    <div>
        <div style="margin-top: 410px;
        padding: 0px 23px">
            <table class="header-table">
                <tbody>
                    <tr>
                        <th style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">Booking Detail</th>
                        <th></th>
                        <th style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">Trip Detail</th>
                        <th></th>
                    </tr>
                    <tr>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Booking ID:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{ $data->crm_id }}</td>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Trip Name:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{$data->product->name}}</td>
                    </tr>
                    <tr>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Reservation ID:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{ preg_match('/_(\d+)$/', $data->crm_id, $matches) ? $matches[1] : '' }}</td>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Car Type:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{$data->car->name}}</td>
                    </tr>
                    {{--  --}}
                    <tr>
                        <th style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">Customer Detail</th>
                        <td></td>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Service Date:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{Carbon\Carbon::parse($data->service_date)->format('d F Y')}}</td>
                    </tr>
                    <tr>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Customer Name:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{$data->booking->customer->name}}</td>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Pick up time:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{$data->pickup_time ? $data->pickup_time : '-'}}</td>
                    </tr>
                    <tr>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Contact:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{$data->booking->customer->phone_number}}</td>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Pick up location:</td>
                        <td></td>
                    </tr>
                    {{--  --}}
                    <tr>
                        <th style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">Payment & Expense</th>
                        <td></td>
                        <td colspan="2" rowspan="2" style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">
                           {{$data->pickup_location ? $data->pickup_location : '-'}}
                        </td>
                    </tr>

                    <tr>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Vendor Name:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{ $data->reservationCarInfo->supplier_name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Expense to Driver:</td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">{{ $total_cost }}</td>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">Route Plan:</td>
                    </tr>
                    <tr>
                        <th style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">Payment Method:</th>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important;color:green">{{ $data->payment_method }}</td>
                        <td colspan="2" rowspan="3" style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">
                           {{ $data->reservationInfo->route_plan ?? '-' }}
                        </td>
                    </tr>
                    @if($data->payment_method == 'Cash')
                    <tr>
                        <td style="width:50%;font-size:16px;padding-bottom:12px!important">To Collect:
                        </td>
                        <td style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important;color:green">{{ $sale_price }}</td>
                    </tr>
                    @endif
                    {{--  --}}
                    <tr></tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="2" style="width:50%;font-size:16px;padding-bottom:12px!important">Special Request:
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td colspan="2" style="width:50%;font-size:16px;font-weight:bold;padding-bottom:12px!important">
                           {{$data->special_request ? $data->special_request : '-'}}
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
