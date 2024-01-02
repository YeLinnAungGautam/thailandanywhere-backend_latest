<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Notify Email</title>

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
    </style>

</head>

<body>
    <div>
        <p>Dear Reservation Manager of {{ $booking_item->product->name }}</p>

        <p>Greetings from Thailand Anywhere travel and tour.</p>
        <p>
            We are pleased to book the tickets for our customers as per
            following description ka.
        </p>

        @if ($booking_item->product_type == 'App\Models\EntranceTicket')
            <div class="space-y-1">
                <p>
                    Date :
                    <span class="font-semibold">{{ $booking_item->service_date }}</span>
                </p>
                <p>
                    Ticket :
                    <span class="font-semibold">{{ $booking_item->variation->name ?? '' }}
                        {{ $booking_item->room->name ?? '' }}</span>
                </p>
                <p>
                    Total :
                    <span class="font-semibold">{{ $booking_item->quantity }}</span>
                </p>
                <p>
                    Name :
                    <span class="font-semibold">{{ $booking_item->booking->customer->name }}</span>
                </p>
            </div>
        @endif

        @if ($booking_item->product_type == 'App\Models\Hotel')
            <div class="space-y-1">
                <p>
                    Check In :
                    <span class="font-semibold">{{ $booking_item->checkin_date }}</span>
                </p>
                <p>
                    Check Out :
                    <span class="font-semibold">{{ $booking_item->checkout_date }}</span>
                </p>
                <p>
                    Total :
                    <span class="font-semibold">{{ $booking_item->quantity }} rooms &
                        {{ $total_nights }} nights</span>
                </p>
                <p>
                    Name :
                    <span class="font-semibold">{{ $booking_item->booking->customer->name ?? '' }} &
                        {{ $booking_item->reservationCustomerPassport->count() }} passports</span>
                </p>
                <p>
                    Room Type :
                    <span class="font-semibold">{{ $booking_item->variation->name ?? '' }}
                        {{ $booking_item->room->name ?? '-' }}</span>
                </p>
                <p>
                    Special Request :
                    <span class="font-semibold">{{ $booking_item->special_request }}</span>
                </p>
            </div>
        @endif

        <p>Passport and payment slips are attached with this email .</p>

        @if ($booking_item->product_type == 'App\Models\EntranceTicket')
            <p class="font-semibold italic">
                Please kindly arrange and invoice & voucher for our clients
                accordingly .
            </p>
        @endif

        @if ($booking_item->product_type == 'App\Models\Hotel')
            <p class="font-semibold italic">
                Please arrange the invoice and confirmation letter ka.
            </p>
        @endif

        <p>
            Should there be anything more required you can call us at
            +66983498197 and LINE ID 58858380 .
        </p>
    </div>


</body>

</html>
