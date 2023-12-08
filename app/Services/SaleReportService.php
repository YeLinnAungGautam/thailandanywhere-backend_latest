<?php
namespace App\Services;

use App\Http\Resources\BookingResource;
use App\Models\Admin;
use App\Models\Booking;
use Carbon\Carbon;
use DateTime;
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

    public function getSaleData(): array
    {
        $sales = Booking::query()
            ->whereBetween('created_at', [$this->start_date, $this->end_date])
            ->select(
                'created_by',
                DB::raw('SUM(grand_total) as total'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as sale_date')
            )
            ->groupBy('created_by', 'created_at')
            ->get();

        return $this->generateSaleResponse($sales);
    }

    public function getSaleCountData(): array
    {
        $sales = Booking::query()
            ->whereBetween('created_at', [$this->start_date, $this->end_date])
            ->select(
                'created_by',
                DB::raw('COUNT(grand_total) as total'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as sale_date')
            )
            ->groupBy('created_by', 'created_at')
            ->get();

        return $this->generateSaleResponse($sales);
    }

    public function getBookingData(): array
    {
        $sales = Booking::query()
            ->whereBetween('created_at', [$this->start_date, $this->end_date])
            ->select(
                'created_by',
                DB::raw('COUNT(id) as total'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as sale_date')
            )
            ->groupBy('created_by', 'created_at')
            ->get();

        return $this->generateSaleResponse($sales);
    }

    public function getReservationsData(): array
    {
        $data = Booking::query()->whereDate('created_at', date($this->date))->get();

        $results = BookingResource::collection($data);

        $items = [];
        $one = [];
        foreach($results as $res) {
            foreach($res->items as $res1) {
                $reserve_types = substr($res1->product_type, 11);

                if($reserve_types == 'Hotel') {

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

        foreach($count_bookings as $value) {
            $booking[] = $value;
        }

        $new_array = [];
        foreach ($one as $value) {
            if(array_key_exists($value['product_type'], $new_array)) {
                $value['price'] += $new_array[$value['product_type']]['price'];
            }
            $new_array[$value['product_type']] = $value;
        }

        foreach($new_array as $res) {
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

    private function getDaysOfMonth(): array
    {
        $dates = Carbon::parse($this->date)->startOfMonth()
            ->daysUntil(Carbon::parse($this->date)->endOfMonth())
            ->map(fn ($date) => $date->format('Y-m-d'));

        return iterator_to_array($dates);
    }

    private function generateSaleResponse($sales): array
    {
        $result = [];
        $agents = Admin::agentOnly()->pluck('name', 'id')->toArray();

        foreach ($this->getDaysOfMonth() as $date) {
            $agent_result = [];

            foreach($agents as $agent_id => $agent_name) {
                $sale_records = $sales->where('sale_date', $date)->where('created_by', $agent_id);

                $agent_result[] = [
                    'name' => $agent_name,
                    'total' => $sale_records->isEmpty() ? 0 : $sale_records->sum('total')
                ];
            }

            $result[] = [
                'date' => $date,
                'agents' => $agent_result
            ];
        }

        return $result;
    }
}
