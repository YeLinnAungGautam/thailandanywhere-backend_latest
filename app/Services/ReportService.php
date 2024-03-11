<?php
namespace App\Services;

use App\Models\Airline;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\EntranceTicket;
use App\Models\Hotel;
use App\Models\PrivateVanTour;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public static function getSalesByAgent(string $date)
    {
        return Booking::query()
            ->with('createdBy:id,name')
            ->groupBy('created_by')
            ->whereDate('created_at', Carbon::parse($date)->format('Y-m-d'))
            ->selectRaw('created_by, GROUP_CONCAT(id) AS booking_ids, SUM(grand_total) as total, COUNT(*) as total_booking')
            ->get();
    }

    public static function getUnpaidBooking(string $daterange)
    {
        $dates = explode(',', $daterange);

        $start_date = Carbon::parse($dates[0])->format('Y-m-d');
        $end_date = Carbon::parse($dates[1])->format('Y-m-d');
        $today_date = Carbon::now()->format('Y-m-d');

        return Booking::query()
            ->with('createdBy:id,name')
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->where('balance_due_date', '<', $today_date)
            ->whereIn('payment_status', ['partially_paid', 'not_paid'])
            ->groupBy('created_by')
            ->selectRaw('created_by, GROUP_CONCAT(id) AS booking_ids, SUM(balance_due) as total_balance, COUNT(*) as total_booking')
            ->get();
    }

    public static function getCountReport(string $daterange): array
    {
        $dates = explode(',', $daterange);
        $start_date = Carbon::parse($dates[0])->format('Y-m-d');
        $end_date = Carbon::parse($dates[1])->format('Y-m-d');

        $booking_count = Booking::whereBetween('created_at', [$start_date, $end_date])->count();
        $private_van_tour_sale_count = BookingItem::where('product_type', PrivateVanTour::class)->whereBetween('created_at', [$start_date, $end_date])->count();
        $attraction_sale_count = BookingItem::where('product_type', EntranceTicket::class)->whereBetween('created_at', [$start_date, $end_date])->count();
        $hotel_sale_count = BookingItem::where('product_type', Hotel::class)->whereBetween('created_at', [$start_date, $end_date])->count();
        $air_ticket_sale_count = BookingItem::where('product_type', Airline::class)->whereBetween('created_at', [$start_date, $end_date])->count();

        return [
            'booking_count' => $booking_count,
            'van_tour_sale_count' => $private_van_tour_sale_count,
            'attraction_sale_count' => $attraction_sale_count,
            'hotel_sale_count' => $hotel_sale_count,
            'air_ticket_sale_count' => $air_ticket_sale_count,
        ];
    }

    public static function getTopSellingProduct(string $daterange, string|null $product_type = null, string|int|null $limit)
    {
        $dates = explode(',', $daterange);

        $start_date = Carbon::parse($dates[0])->format('Y-m-d');
        $end_date = Carbon::parse($dates[1])->format('Y-m-d');

        $products = BookingItem::query()
            ->with('product:id,name')
            ->when($product_type, fn ($query) => $query->where('product_type', $product_type))
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->groupBy(
                'product_id',
                'product_type',
                'variation_id',
                'car_id',
                'room_id',
                'ticket_id'
            )
            ->select(
                'product_id',
                'product_type',
                'variation_id',
                'car_id',
                'room_id',
                'ticket_id',
                DB::raw('GROUP_CONCAT(id) AS reservation_ids'),
                DB::raw('GROUP_CONCAT(selling_price) AS selling_prices'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->orderByDesc('total_quantity')
            ->paginate($limit ?? 5);

        return $products;
    }
}
