<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductReportController extends Controller
{
    use HttpResponses;
    /**
     * Get daily product sales data with DB query for better performance
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyProductSalesDB(Request $request)
    {
        try {
            $request->validate([
                'year' => 'nullable|integer',
                'month' => 'nullable|integer|between:1,12',
                'created_bys' => 'nullable',
            ]);

            $year = $request->year ?? Carbon::now()->year;
            $month = $request->month ?? Carbon::now()->month;

            // Handle the created_bys parameter correctly regardless of format
            $createdBys = [];
            if ($request->has('created_bys')) {
                // If string, try to parse it
                if (is_string($request->created_bys)) {
                    // Handle comma-separated or JSON format
                    if (strpos($request->created_bys, '[') !== false) {
                        // JSON format
                        $createdBys = json_decode($request->created_bys, true) ?? [];
                    } else {
                        // Comma-separated format
                        $createdBys = array_map('trim', explode(',', $request->created_bys));
                    }
                } else if (is_array($request->created_bys)) {
                    // Already an array
                    $createdBys = $request->created_bys;
                }

                // Ensure all values are integers
                $createdBys = array_filter(array_map(function($value) {
                    return is_numeric($value) ? (int)$value : null;
                }, $createdBys));
            }

            // Calculate start and end dates
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

            // Use a direct DB query for better performance
            $query = DB::table('bookings')
                ->join('booking_items', 'bookings.id', '=', 'booking_items.booking_id')
                ->select(
                    DB::raw('DAY(bookings.created_at) as day'),
                    DB::raw('SUBSTR(booking_items.product_type, 12) as product_type'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereBetween('bookings.created_at', [$startDate, $endDate]);

            // Filter by user IDs if provided
            if (!empty($createdBys)) {
                $query->whereIn('bookings.created_by', $createdBys);
            }

            $results = $query->groupBy('day', 'product_type')
                ->orderBy('day')
                ->get();

            // Initialize the daily data array
            $daysInMonth = $endDate->day;
            $dailyData = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentDate = Carbon::createFromDate($year, $month, $day);
                $dailyData[$day] = [
                    'day' => $day,
                    'date' => $currentDate->format('Y-m-d'),
                    'day_name' => $currentDate->format('l'),
                    'Hotel' => 0,
                    'EntranceTicket' => 0,
                    'PrivateVanTour' => 0
                ];
            }

            // Fill in actual counts from results
            foreach ($results as $result) {
                $day = $result->day;
                $productType = $result->product_type;

                if (isset($dailyData[$day]) && in_array($productType, ['Hotel', 'EntranceTicket', 'PrivateVanTour'])) {
                    $dailyData[$day][$productType] = $result->count;
                }
            }

            // Calculate totals and add to each day
            $responseData = [];
            foreach ($dailyData as $day => $data) {
                $data['total'] = $data['Hotel'] + $data['EntranceTicket'] + $data['PrivateVanTour'];
                $responseData[] = $data;
            }

            // Format month name for response message
            $monthName = Carbon::createFromDate($year, $month, 1)->format('F');

            return $this->success(
                [
                    'month_info' => [
                        'year' => $year,
                        'month' => $month,
                        'month_name' => $monthName
                    ],
                    'daily_data' => array_values($responseData),
                    'filtered_by_users' => !empty($createdBys) ? $createdBys : null
                ],
                "Daily Product Sales for $monthName $year" . (!empty($createdBys) ? " (Filtered by selected users)" : "")
            );
        } catch (\Exception $e) {
            Log::error('Error in getDailyProductSalesDB: ' . $e->getMessage());
            return $this->error('An error occurred while processing your request', 500, $e->getMessage());
        }
    }

    /**
     * Get the top selling products for each product type
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopProductsByType(Request $request)
    {
        try {
            $request->validate([
                'year' => 'nullable|integer',
                'month' => 'nullable|integer|between:1,12',
                'limit' => 'nullable|integer|min:1|max:50',
                'created_bys' => 'nullable',
            ]);

            $year = $request->year ?? Carbon::now()->year;
            $month = $request->month;
            $limit = $request->limit ?? 10; // Default to top 10 products per type

            // Handle the created_bys parameter
            $createdBys = [];
            if ($request->has('created_bys')) {
                // If string, try to parse it
                if (is_string($request->created_bys)) {
                    // Handle comma-separated or JSON format
                    if (strpos($request->created_bys, '[') !== false) {
                        // JSON format
                        $createdBys = json_decode($request->created_bys, true) ?? [];
                    } else {
                        // Comma-separated format
                        $createdBys = array_map('trim', explode(',', $request->created_bys));
                    }
                } else if (is_array($request->created_bys)) {
                    // Already an array
                    $createdBys = $request->created_bys;
                }

                // Ensure all values are integers
                $createdBys = array_filter(array_map(function($value) {
                    return is_numeric($value) ? (int)$value : null;
                }, $createdBys));
            }

            // Define the product types with their exact table names
            $productTypeMappings = [
                'App\Models\Hotel' => 'hotels',
                'App\Models\EntranceTicket' => 'entrance_tickets',
                'App\Models\PrivateVanTour' => 'private_van_tours'
            ];

            // Build the base query
            $baseQuery = DB::table('bookings')
                ->whereYear('bookings.created_at', $year);

            // Filter by user IDs if provided
            if (!empty($createdBys)) {
                $baseQuery->whereIn('bookings.created_by', $createdBys);
            }

            // If a specific month is requested
            if ($month) {
                $baseQuery->whereMonth('bookings.created_at', $month);
                $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');
            } else {
                $periodLabel = "Year $year";
            }

            // Get top products for each type
            $resultsByType = [];

            foreach ($productTypeMappings as $type => $tableName) {
                $modelName = substr($type, strrpos($type, '\\') + 1);

                $query = clone $baseQuery;

                $results = $query->join('booking_items', 'bookings.id', '=', 'booking_items.booking_id')
                    ->join($tableName, function($join) use ($type, $tableName) {
                        $join->on('booking_items.product_id', '=', "$tableName.id")
                            ->where('booking_items.product_type', '=', $type);
                    })
                    ->select(
                        "$tableName.id as product_id",
                        "$tableName.name as product_name",
                        'booking_items.product_type',
                        DB::raw('SUBSTR(booking_items.product_type, 12) as type_name'),
                        DB::raw('COUNT(booking_items.id) as count'),
                        DB::raw('SUM(booking_items.selling_price * booking_items.quantity) as total_sales'),
                        DB::raw('AVG(booking_items.selling_price) as average_price')
                    )
                    ->groupBy("$tableName.id", "$tableName.name", 'booking_items.product_type')
                    ->orderBy('count', 'desc')
                    ->orderBy('total_sales', 'desc')
                    ->limit($limit)
                    ->get();

                $resultsByType[$modelName] = $results;
            }

            return $this->success(
                [
                    'year' => $year,
                    'month' => $month ? $month : null,
                    'month_name' => $month ? Carbon::createFromDate($year, $month, 1)->format('F') : null,
                    'limit' => $limit,
                    'product_types' => $resultsByType,
                    'filtered_by_users' => !empty($createdBys) ? $createdBys : null
                ],
                "Top $limit Products by Type for $periodLabel" . (!empty($createdBys) ? " (Filtered by selected users)" : "")
            );
        } catch (\Exception $e) {
            Log::error('Error in getTopProductsByType: ' . $e->getMessage());
            return $this->error('An error occurred while processing your request', 500, $e->getMessage());
        }
    }

    /**
     * Get the top selling products by type for each month
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonthlyTopProductsByType(Request $request)
    {
        try {
            $request->validate([
                'year' => 'nullable|integer',
                'product_type' => 'nullable|in:Hotel,EntranceTicket,PrivateVanTour',
                'limit' => 'nullable|integer|min:1|max:30',
                'created_bys' => 'nullable',
            ]);

            $year = $request->year ?? Carbon::now()->year;
            $requestedType = $request->product_type;
            $limit = $request->limit ?? 10; // Default to top 10 products

            // Handle the created_bys parameter
            $createdBys = [];
            if ($request->has('created_bys')) {
                // If string, try to parse it
                if (is_string($request->created_bys)) {
                    // Handle comma-separated or JSON format
                    if (strpos($request->created_bys, '[') !== false) {
                        // JSON format
                        $createdBys = json_decode($request->created_bys, true) ?? [];
                    } else {
                        // Comma-separated format
                        $createdBys = array_map('trim', explode(',', $request->created_bys));
                    }
                } else if (is_array($request->created_bys)) {
                    // Already an array
                    $createdBys = $request->created_bys;
                }

                // Ensure all values are integers
                $createdBys = array_filter(array_map(function($value) {
                    return is_numeric($value) ? (int)$value : null;
                }, $createdBys));
            }

            // Define the product types with their exact table names
            $productTypeMappings = [
                'App\Models\Hotel' => 'hotels',
                'App\Models\EntranceTicket' => 'entrance_tickets',
                'App\Models\PrivateVanTour' => 'private_van_tours'
            ];

            // Filter product types if a specific one is requested
            if ($requestedType) {
                $fullType = 'App\Models\\' . $requestedType;
                $filteredMappings = [];

                if (isset($productTypeMappings[$fullType])) {
                    $filteredMappings[$fullType] = $productTypeMappings[$fullType];
                }

                $productTypeMappings = $filteredMappings;
            }

            $monthlyData = [];

            // Initialize data structure for all months
            for ($month = 1; $month <= 12; $month++) {
                $monthName = Carbon::createFromDate($year, $month, 1)->format('F');
                $monthlyData[$monthName] = [
                    'month_number' => $month,
                    'month_name' => $monthName,
                    'product_types' => []
                ];

                foreach ($productTypeMappings as $type => $tableName) {
                    $modelName = substr($type, strrpos($type, '\\') + 1);
                    $monthlyData[$monthName]['product_types'][$modelName] = [];
                }
            }

            // Process each product type
            foreach ($productTypeMappings as $type => $tableName) {
                $modelName = substr($type, strrpos($type, '\\') + 1);

                // Get the product counts for each month in the year
                $query = DB::table('bookings')
                    ->join('booking_items', 'bookings.id', '=', 'booking_items.booking_id')
                    ->join($tableName, function($join) use ($type, $tableName) {
                        $join->on('booking_items.product_id', '=', "$tableName.id")
                            ->where('booking_items.product_type', '=', $type);
                    })
                    ->select(
                        DB::raw('MONTH(bookings.created_at) as month'),
                        "$tableName.id as product_id",
                        "$tableName.name as product_name",
                        DB::raw('COUNT(booking_items.id) as count'),
                        DB::raw('SUM(booking_items.selling_price * booking_items.quantity) as total_sales')
                    )
                    ->whereYear('bookings.created_at', $year);

                // Filter by user IDs if provided
                if (!empty($createdBys)) {
                    $query->whereIn('bookings.created_by', $createdBys);
                }

                $results = $query->groupBy('month', "$tableName.id", "$tableName.name")
                    ->orderBy('month')
                    ->orderBy('count', 'desc')
                    ->get();

                // Process and organize the results by month
                foreach ($results as $result) {
                    $monthNumber = $result->month;
                    $monthName = Carbon::createFromDate($year, $monthNumber, 1)->format('F');

                    // Check if we need to add more results for this month and product type
                    if (count($monthlyData[$monthName]['product_types'][$modelName]) < $limit) {
                        $monthlyData[$monthName]['product_types'][$modelName][] = [
                            'product_id' => $result->product_id,
                            'product_name' => $result->product_name,
                            'count' => $result->count,
                            'total_sales' => round($result->total_sales, 2),
                            'average_price' => $result->count > 0 ? round($result->total_sales / $result->count, 2) : 0
                        ];
                    }
                }
            }

            // Convert to sequential array and sort by month number
            $responseData = array_values($monthlyData);
            usort($responseData, function($a, $b) {
                return $a['month_number'] <=> $b['month_number'];
            });

            return $this->success(
                [
                    'year' => $year,
                    'product_type' => $requestedType,
                    'limit' => $limit,
                    'monthly_data' => $responseData,
                    'filtered_by_users' => !empty($createdBys) ? $createdBys : null
                ],
                "Monthly Top $limit Products" . ($requestedType ? " for $requestedType" : " by Type") . " in $year" .
                (!empty($createdBys) ? " (Filtered by selected users)" : "")
            );
        } catch (\Exception $e) {
            Log::error('Error in getMonthlyTopProductsByType: ' . $e->getMessage());
            return $this->error('An error occurred while processing your request', 500, $e->getMessage());
        }
    }
}
