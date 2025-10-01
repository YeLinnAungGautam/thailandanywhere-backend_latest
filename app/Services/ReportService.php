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
    public static function getSalesByAgent(string $daterange)
    {
        $dates = explode(',', $daterange);

        $start_date = Carbon::parse($dates[0])->format('Y-m-d');
        $end_date = Carbon::parse($dates[1])->format('Y-m-d');

        $bookings = Booking::query()
            ->join('admins', 'bookings.created_by', '=', 'admins.id')
            ->with('createdBy:id,name,target_amount')
            ->whereBetween('bookings.created_at', [$start_date, $end_date])
            ->selectRaw(
                'bookings.created_by,
                admins.target_amount as target_amount,
                GROUP_CONCAT(bookings.id) AS booking_ids,
                GROUP_CONCAT(CONCAT(DATE(bookings.created_at), "__", bookings.grand_total)) AS created_at_grand_total,
                SUM(bookings.grand_total) as total,
                COUNT(*) as total_booking',
            )
            ->groupBy('bookings.created_by', 'admins.target_amount')
            ->get();

        foreach($bookings as $booking) {
            $test = self::adminHasOverratedTargetAmount($booking);

            $booking->over_target_count = $test;
        }

        return $bookings;
    }

    public static function getProductTypeSales(string $daterange)
    {
        $dates = explode(',', $daterange);

        $start_date = Carbon::parse($dates[0])->format('Y-m-d');
        $end_date = Carbon::parse($dates[1])->format('Y-m-d');

        $results = BookingItem::query()
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', '!=', 'fully_paid')
            ->whereDate('booking_items.service_date', '>=', $start_date)
            ->whereDate('booking_items.service_date', '<=', $end_date)
            ->whereNull('booking_items.deleted_at')
            ->groupBy('service_date', 'booking_items.product_type')
            ->select(
                DB::raw('DATE(booking_items.service_date) as date'),
                'booking_items.product_type',
                DB::raw('COUNT(booking_items.id) as booking_item_count'),
                DB::raw('SUM(CAST(booking_items.total_cost_price AS DECIMAL(10,2))) as total_expense'),
                DB::raw('COUNT(DISTINCT booking_items.booking_id) as booking_count'),
                DB::raw('SUM(booking_items.quantity) as total_quantity'),
                DB::raw('SUM(booking_items.amount - booking_items.total_cost_price) as total_profit')
            )
            ->orderBy('service_date')
            ->orderBy('booking_items.product_type')
            ->get();

        // Get remaining expense for non-fully-paid bookings with groups that have cash images
        $remainingExpense = BookingItem::query()
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')

            ->where('bookings.payment_status', 'fully_paid')
            ->where('booking_items.payment_status', '!=', 'fully_paid')
            ->whereDate('booking_items.service_date', '>=', $start_date)
            ->whereDate('booking_items.service_date', '<=', $end_date)
            ->whereNull('booking_items.deleted_at')
            ->groupBy('service_date', 'booking_items.product_type')
            ->select(
                DB::raw('DATE(booking_items.service_date) as date'),
                'booking_items.product_type',
                DB::raw('SUM(CAST(booking_items.total_cost_price AS DECIMAL(10,2))) as remain_expense_total')
            )
            ->get()
            ->groupBy('date')
            ->map(function($items) {
                return $items->keyBy('product_type');
            });

        // Group by date and merge with remaining expense
        $grouped = $results->groupBy('date')->map(function ($items, $date) use ($remainingExpense) {
            return [
                'date' => $date,
                'product_types' => $items->map(function ($item) use ($remainingExpense, $date) {
                    $remainExpense = $remainingExpense->get($date)?->get($item->product_type)?->remain_expense_total ?? 0;

                    return [
                        'product_type' => $item->product_type,
                        'booking_count' => $item->booking_count,
                        'booking_item_count' => $item->booking_item_count,
                        'total_quantity' => $item->total_quantity,
                        'total_expense' => $item->total_expense,
                        'remain_expense_total' => $remainExpense,
                        'total_profit' => $item->total_profit,
                    ];
                })->values()
            ];
        })->values();

        return $grouped;
    }

    public static function getProductTypeDetail(string $date, string $product_type, string $type)
    {
        $query = BookingItem::query()
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->whereDate('booking_items.service_date', '=', $date)
            ->where('booking_items.product_type', '=', $product_type)
            ->whereNull('booking_items.deleted_at');

        // Apply filters based on type
        switch ($type) {
            case 'remain_expense':
                // Items where booking is fully paid but item is not
                $query->where('bookings.payment_status', 'fully_paid')
                      ->where('booking_items.payment_status', '!=', 'fully_paid');
                break;

            case 'total_expense':
            case 'all':
            default:
                // All items matching the date and product type
                $query->where('bookings.payment_status', 'fully_paid')
                      ->where('booking_items.payment_status', '!=', 'fully_paid');
                break;
        }

        $results = $query->select(
                'booking_items.id',
                'booking_items.booking_id',
                'bookings.booking_number',
                'booking_items.service_date',
                'booking_items.product_type',
                'booking_items.product_name',
                'booking_items.quantity',
                'booking_items.amount',
                'booking_items.total_cost_price',
                DB::raw('(booking_items.amount - booking_items.total_cost_price) as profit'),
                'booking_items.payment_status as item_payment_status',
                'bookings.payment_status as booking_payment_status'
            )
            ->orderBy('booking_items.id')
            ->get();

        return [
            'date' => $date,
            'product_type' => $product_type,
            'type' => $type,
            'total_items' => $results->count(),
            'total_expense' => $results->sum('total_cost_price'),
            'total_profit' => $results->sum('profit'),
            'total_quantity' => $results->sum('quantity'),
            'items' => $results
        ];
    }

    public static function getUnpaidBooking(string $daterange, string|null $agent_id, string|null $service_daterange)
    {
        $dates = explode(',', $daterange);

        $start_date = Carbon::parse($dates[0])->format('Y-m-d');
        $end_date = Carbon::parse($dates[1])->format('Y-m-d');
        $today_date = Carbon::now()->format('Y-m-d');

        return Booking::query()
            ->with('createdBy:id,name')
            ->when($agent_id, fn ($query) => $query->where('created_by', $agent_id))
            ->when($service_daterange, function ($query) use ($service_daterange) {
                $query->whereHas('items', function ($q) use ($service_daterange) {
                    $service_dates = explode(',', $service_daterange);

                    $q->whereDate('service_date', '>=', Carbon::parse($service_dates[0])->format('Y-m-d'))
                        ->whereDate('service_date', '<=', Carbon::parse($service_dates[1])->format('Y-m-d'));
                });
            })
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

    private static function adminHasOverratedTargetAmount($booking)
    {
        $over_target_count = 0;
        $created_grand_total = explode(',', $booking->created_at_grand_total);

        $collection = collect($created_grand_total);

        // Group by the date prefix and map to get only the values after '__'
        $grouped = $collection->groupBy(function ($item) {
            // Extract the date prefix by splitting on '__'
            return explode('__', $item)[0];
        })->toArray();

        $filteredDates = [];

        foreach ($grouped as $date => $entries) {
            $total = 0;
            foreach ($entries as $entry) {
                $parts = explode('__', $entry);
                if (isset($parts[1])) {
                    $total += (int)$parts[1];
                }
            }
            if ($total >= $booking->target_amount) {
                $filteredDates[$date] = count($entries);
            }
        }

        return array_sum($filteredDates);
    }
}
