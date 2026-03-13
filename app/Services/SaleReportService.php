<?php
namespace App\Services;

use App\Http\Resources\BookingResource;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\BookingItemGroup;
use App\Models\EntranceTicket;
use App\Models\Hotel;
use App\Models\PrivateVanTour;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SaleReportService
{
    protected $date;
    protected $start_date;
    protected $end_date;

    public function __construct(string $date)
    {
        $this->date = $date;
        $this->start_date = Carbon::parse($date)->startOfMonth()->format('Y-m-d');
        $this->end_date = Carbon::parse($date)->endOfMonth()->format('Y-m-d');
    }

    public function getSaleData($created_by = null): array
    {
        $sales = Booking::query()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('created_by', explode(',', $created_by));
            })
            ->whereBetween('created_at', [$this->start_date, $this->end_date])
            ->select(
                'created_by',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(grand_total) as total'),
                DB::raw('SUM(deposit) as total_deposit'),
                DB::raw('SUM(balance_due) as total_balance'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as sale_date')
            )
            ->groupBy('created_by', 'created_at')
            ->get();

        return $this->generateSaleResponse($sales, $created_by, true, true);
    }

    public function getSaleCountData($created_by = null): array
    {
        $sales = Booking::query()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('created_by', explode(',', $created_by));
            })
            ->whereBetween('created_at', [$this->start_date, $this->end_date])
            ->select(
                'created_by',
                DB::raw('COUNT(grand_total) as total'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as sale_date')
            )
            ->groupBy('created_by', 'created_at')
            ->get();

        return $this->generateSaleResponse($sales, $created_by);
    }

    public function getBookingData($created_by = null): array
    {
        $sales = Booking::query()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('created_by', explode(',', $created_by));
            })
            ->whereBetween('created_at', [$this->start_date, $this->end_date])
            ->select(
                'created_by',
                DB::raw('COUNT(id) as total'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as sale_date')
            )
            ->groupBy('created_by', 'created_at')
            ->get();

        return $this->generateSaleResponse($sales, $created_by);
    }

    public function getReservationsData(): array
    {
        $data = Booking::query()->whereDate('created_at', date($this->date))->get();

        $results = BookingResource::collection($data);

        $items = [];
        $one = [];
        foreach ($results as $res) {
            foreach ($res->items as $res1) {
                $reserve_types = substr($res1->product_type, 11);

                if ($reserve_types == 'Hotel') {

                    $datetime1 = new DateTime($res1->checkin_date);
                    $datetime2 = new DateTime($res1->checkout_date);
                    $interval = $datetime1->diff($datetime2);
                    $days = $interval->format('%a');

                    $price = $res1->quantity * $res1->selling_price * $days;

                } else {

                    $price = $res1->quantity * $res1->selling_price;

                }

                $one[] = [
                    'product_type' => $reserve_types,
                    'price' => $price
                ];

                $items[] = [
                    'product_type' => $reserve_types,
                    'prices' => $price
                ];
            }
        }

        $count_bookings = array_count_values(array_column($items, 'product_type'));

        foreach ($count_bookings as $value) {
            $booking[] = $value;
        }

        $new_array = [];
        foreach ($one as $value) {
            if (array_key_exists($value['product_type'], $new_array)) {
                $value['price'] += $new_array[$value['product_type']]['price'];
            }
            $new_array[$value['product_type']] = $value;
        }

        foreach ($new_array as $res) {
            $type[] = $res['product_type'];
            $prices[] = $res['price'];

        }

        $data = [
            'agents' => isset($type) ? $type : null,
            'amount' => isset($booking) ? $booking : null,
            'prices' => isset($prices) ? $prices : null,
        ];

        return $data;
    }

    public static function getSaleRank(Admin $admin, ?string $date = null): int
    {
        $rank_index = self::getSaleCount($date)
            ->where('created_by', $admin->id)
            ->keys()
            ->first();

        return is_null($rank_index) ? 0 : $rank_index + 1;
    }

    public static function getSaleCount(?string $date = null): Collection
    {
        $date = $date ?? Carbon::now()->format('Y-m-d');

        return Booking::query()
            ->whereDate('created_at', $date)
            ->groupBy('created_by')
            ->orderByRaw('total DESC')
            ->select(
                'created_by',
                DB::raw('COUNT(grand_total) as total')
            )
            ->get();
    }

    public function getProductSaleCount(string $product_type)
    {
        switch ($product_type) {
            case 'private_van_tour':
                $product_type = PrivateVanTour::class;

                break;

            case 'hotel':
                $product_type = Hotel::class;

                break;

            case 'attraction':
                $product_type = EntranceTicket::class;

                break;

            default:
                throw new Exception('Invalid product type');

                break;
        }

        return Booking::query()
            ->whereHas('items', function ($q) use ($product_type) {
                $q->where('product_type', $product_type);
            })
            ->whereDate('created_at', $this->date)
            ->get();
    }

    public function getAirlineSaleData($created_by = null): array
    {
        $sales = Booking::query()
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('bookings.created_by', explode(',', $created_by));
            })
            ->join('booking_items', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('booking_items.product_type', 'App\Models\Airline')
            ->whereBetween('bookings.created_at', [$this->start_date, $this->end_date])
            ->select(
                'bookings.created_by',
                DB::raw('COUNT(DISTINCT bookings.id) as total_count'),
                DB::raw('SUM(booking_items.amount) as total'),
                DB::raw('0 as total_deposit'),
                DB::raw('SUM(booking_items.amount) as total_balance'),
                DB::raw('DATE_FORMAT(bookings.created_at, "%Y-%m-%d") as sale_date')
            )
            ->groupBy('bookings.created_by', DB::raw('DATE_FORMAT(bookings.created_at, "%Y-%m-%d")'))
            ->get();

        return $this->generateSaleResponse($sales, $created_by, true, true);
    }

    private function getDaysOfMonth(): array
    {
        $dates = Carbon::parse($this->date)->startOfMonth()
            ->daysUntil(Carbon::parse($this->date)->endOfMonth())
            ->map(fn ($date) => $date->format('Y-m-d'));

        return iterator_to_array($dates);
    }

    public function getInclusiveSaleData(?string $created_by = null, string $search_by = 'created_at'): array
    {
        // $search_by = 'created_at' | 'inclusive_start_date'
        $dateColumn = $search_by === 'inclusive_start_date'
            ? 'inclusive_start_date'
            : 'created_at';

        $sales = Booking::query()
            ->where('is_inclusive', 1)
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('created_by', explode(',', $created_by));
            })
            ->whereBetween($dateColumn, [$this->start_date, $this->end_date])
            ->select(
                'created_by',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(grand_total) as total'),
                DB::raw('SUM(deposit) as total_deposit'),
                DB::raw('SUM(balance_due) as total_balance'),
                DB::raw("DATE_FORMAT({$dateColumn}, '%Y-%m-%d') as sale_date")
            )
            ->groupBy('created_by', DB::raw("DATE_FORMAT({$dateColumn}, '%Y-%m-%d')"))
            ->get();

        return $this->generateSaleResponse($sales, $created_by, true, true);
    }

    public function getInclusiveDayBookings(string $day, ?string $created_by = null, string $search_by = 'created_at'): \Illuminate\Pagination\LengthAwarePaginator
    {
        $dateColumn = $search_by === 'inclusive_start_date'
            ? 'inclusive_start_date'
            : 'created_at';

        return Booking::query()
            ->with(['customer', 'items.product', 'createdBy'])
            ->where('is_inclusive', 1)
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('created_by', explode(',', $created_by));
            })
            ->whereDate($dateColumn, $day)
            ->paginate(request('limit', 15));
    }

    private function generateSaleResponse($sales, $created_by = null, $with_balance = false, $with_count = false): array
    {
        $result = [];
        $agents = Admin::query()
            // ->agentOnly()
            ->agentAndSaleManager()
            ->when($created_by, function ($q) use ($created_by) {
                // $q->where('id', $created_by);
                $q->whereIn('id', explode(',', $created_by));
            })
            ->pluck('name', 'id')
            ->toArray();

        foreach ($this->getDaysOfMonth() as $date) {
            $agent_result = [];

            foreach ($agents as $agent_id => $agent_name) {
                $sale_records = $sales->where('sale_date', $date)->where('created_by', $agent_id);

                $data_result = [
                    'name' => $agent_name,
                    'total' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total')
                ];

                if ($with_balance) {
                    $data_result += [
                        'total_deposit' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total_deposit'),
                        'total_balance' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total_balance'),
                    ];
                }

                if ($with_count) {
                    $data_result += [
                        'total_count' => $sale_records->sum('total_count'),
                    ];
                }

                // if($with_balance) {
                //     $agent_result[] = [
                //         'name' => $agent_name,
                //         'total_deposit' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total_deposit'),
                //         'total_balance' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total_balance'),
                //         'total' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total')
                //     ];
                // } elseif($with_count) {

                // } else {
                //     $agent_result[] = [
                //         'name' => $agent_name,
                //         'total' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total')
                //     ];
                // }

                $agent_result[] = $data_result;
            }

            $result[] = [
                'date' => $date,
                'agents' => $agent_result
            ];
        }

        return $result;
    }

    public function getExpenseGraphData(
        ?string $created_by = null,
        string $product_type = 'all',
        ?string $expense_status = null,
        ?string $customer_payment_status = null
    ): array {
        $year  = Carbon::parse($this->date)->year;
        $month = Carbon::parse($this->date)->month;

        $startOfMonth = Carbon::create($year, $month)->startOfMonth();
        $endOfMonth   = Carbon::create($year, $month)->endOfMonth();
        $daysInMonth  = $startOfMonth->daysInMonth;

        $productTypeMap = [
            'hotel'           => 'App\Models\Hotel',
            'entrance_ticket' => 'App\Models\EntranceTicket',
        ];

        $filterTypes = $product_type === 'all'
            ? array_values($productTypeMap)
            : [($productTypeMap[$product_type] ?? null)];

        $filterTypes = array_filter($filterTypes);

        $rows = DB::table('booking_item_groups as big')
            ->join('bookings as b', 'big.booking_id', '=', 'b.id')
            ->joinSub(
                DB::table('booking_items')
                    ->select(
                        'group_id',
                        DB::raw('MIN(service_date) as earliest_service_date'),
                        DB::raw('SUM(amount) as total_amount'),
                        DB::raw('SUM(total_cost_price) as total_cost_price')
                    )
                    ->groupBy('group_id'),
                'bi_agg',
                'bi_agg.group_id', '=', 'big.id'
            )
            ->whereIn('big.product_type', $filterTypes)

            // Expense status filter
            ->when($expense_status === 'not_paid', function ($q) {
                $q->where(function ($q) {
                    $q->where('big.expense_status', 'not_paid')
                      ->orWhereNull('big.expense_status');
                });
            })
            ->when($expense_status === 'fully_paid', function ($q) {
                $q->where('big.expense_status', 'fully_paid');
            })
            ->when($expense_status === 'partially_paid', function ($q) {
                $q->where('big.expense_status', 'partially_paid');
            })

            // Customer payment status filter
            ->when($customer_payment_status === 'fully_paid', function ($q) {
                $q->where('b.payment_status', 'fully_paid');
            })
            ->when($customer_payment_status === 'not_paid', function ($q) {
                $q->where(function ($q) {
                    $q->where('b.payment_status', '!=', 'fully_paid')
                      ->orWhereNull('b.payment_status');
                });
            })
            ->when($customer_payment_status === 'partially_paid', function ($q) {
                $q->where('b.payment_status', 'partially_paid');
            })

            // Agent filter
            ->when($created_by, function ($q) use ($created_by) {
                $q->whereIn('b.created_by', explode(',', $created_by));
            })

            // Date range — earliest service date within the month
            ->whereBetween('bi_agg.earliest_service_date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])

            ->select(
                DB::raw('DAY(bi_agg.earliest_service_date) as day'),
                'big.product_type',
                DB::raw('SUM(bi_agg.total_amount) as total_amount'),
                DB::raw('SUM(bi_agg.total_cost_price) as total_cost_price'),
                DB::raw('COUNT(DISTINCT big.id) as total_groups')
            )
            ->groupBy('day', 'big.product_type')
            ->orderBy('day')
            ->get();

        // Build full days scaffold for the month
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $days[$d] = [
                'day'                       => $d,
                'date'                      => Carbon::create($year, $month, $d)->format('Y-m-d'),
                'day_label'                 => Carbon::create($year, $month, $d)->format('D, d M'),
                'combined_total_amount'     => 0,
                'combined_total_cost_price' => 0,
                'combined_total_groups'     => 0,
            ];

            if ($product_type === 'all' || $product_type === 'hotel') {
                $days[$d]['hotel'] = [
                    'total_amount'     => 0,
                    'total_cost_price' => 0,
                    'total_groups'     => 0,
                ];
            }
            if ($product_type === 'all' || $product_type === 'entrance_ticket') {
                $days[$d]['entrance_ticket'] = [
                    'total_amount'     => 0,
                    'total_cost_price' => 0,
                    'total_groups'     => 0,
                ];
            }
        }

        // Fill in actual data
        $reverseMap = array_flip($productTypeMap);

        foreach ($rows as $row) {
            $typeKey = $reverseMap[$row->product_type] ?? null;
            if (!$typeKey || !isset($days[$row->day])) continue;

            $days[$row->day][$typeKey] = [
                'total_amount'     => (float) $row->total_amount,
                'total_cost_price' => (float) $row->total_cost_price,
                'total_groups'     => (int)   $row->total_groups,
            ];

            $days[$row->day]['combined_total_amount']     += (float) $row->total_amount;
            $days[$row->day]['combined_total_cost_price'] += (float) $row->total_cost_price;
            $days[$row->day]['combined_total_groups']     += (int)   $row->total_groups;
        }

        $daysFlat = array_values($days);

        return [
            'year'         => $year,
            'month'        => $month,
            'month_label'  => Carbon::create($year, $month)->format('F Y'),
            'product_type' => $product_type,
            'days'         => $daysFlat,
            'summary'      => [
                'hotel_total_amount'               => collect($daysFlat)->sum(fn($d) => $d['hotel']['total_amount'] ?? 0),
                'hotel_total_cost_price'           => collect($daysFlat)->sum(fn($d) => $d['hotel']['total_cost_price'] ?? 0),
                'entrance_ticket_total_amount'     => collect($daysFlat)->sum(fn($d) => $d['entrance_ticket']['total_amount'] ?? 0),
                'entrance_ticket_total_cost_price' => collect($daysFlat)->sum(fn($d) => $d['entrance_ticket']['total_cost_price'] ?? 0),
                'grand_total_amount'               => collect($daysFlat)->sum(fn($d) => $d['combined_total_amount']),
                'grand_total_cost_price'           => collect($daysFlat)->sum(fn($d) => $d['combined_total_cost_price']),
            ],
        ];
    }


}
