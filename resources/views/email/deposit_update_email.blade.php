<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Deposit Update Email</title>

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

        .hr-line {
            border-top: 1px solid #c6c6c6;
            margin: 10px 0;
        }
    </style>

</head>

<body>
    {{-- <div id="watermark">
        <img src="{{ asset('assets/print.png') }}" height="100%" width="100%" />
    </div> --}}

    <p>Dear Reservation Team,</p>

    <p>The following CRM ID has been {{ $booking->payment_status }}. Please see the reservation items of the sales below.</p>

    @foreach ($booking->items as $item)
        @php
            $booking_item = (new App\Http\Resources\BookingResource($item));

            switch ($booking_item->product_type) {
                case 'App\Models\Hotel':
                    $variation_name = optional($booking_item->room)->name;
                    break;

                case 'App\Models\PrivateVanTour':
                    $variation_name = optional($booking_item->car)->name;
                    break;

                case 'App\Models\EntranceTicket':
                    $variation_name = optional($booking_item->variation)->name;
                    break;

                case 'App\Models\Airline':
                    $variation_name = optional($booking_item->ticket)->price;
                    break;

                default:
                    $variation_name = null;
                    break;
            }

            $link = 'https://sales-admin.thanywhere.com/reservation/update/' . $booking_item->id . '/' . $booking_item->crm_id;
        @endphp

        <div class="hr-line">

        <p>
            {{ $item->crm_id }}: {{ $product_type[$item->product_type] }} > {{ $item->product->name }} > {{ $variation_name ?? '-' }} > {{ number_format($booking_item->calc_sale_price) }} THB
        </p>

        <a href="{{ $link }}" target="_blank">{{ $link }}</a>
    @endforeach

    {{-- <div id="watermark">
        <img src="{{ asset('assets/printf.png') }}" height="100%" width="100%" />
    </div> --}}
</body>

</html>
