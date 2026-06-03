<?php

namespace App\Http\Controllers;

use App\Http\Resources\Accountance\UnpaidResource;
use App\Models\Booking;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceDueOverController extends Controller
{
    use HttpResponses;

    /**
     * GET /api/balance-due-over/graph?year=2024&month=5&admin_id=1,2
     *
     * Returns daily aggregated overdue data grouped by admin,
     * shaped for a stacked bar chart.
     */
    public function graph(Request $request)
    {
        $year    = (int) $request->query('year',  now()->year);
        $month   = (int) $request->query('month', now()->month);
        $adminId = $request->query('admin_id'); // optional comma-separated or array

        // Validate month
        if ($month < 1 || $month > 12) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid month. Must be 1–12.',
            ], 400);
        }

        $query = Booking::query()
            ->select([
                DB::raw('DATE(balance_due_date) as due_date'),
                'created_by',
                DB::raw('SUM(balance_due)  as total_balance_due'),
                DB::raw('COUNT(*)          as total_count'),
            ])
            ->where('payment_status', '!=', 'fully_paid')
            ->whereNotNull('balance_due_date')
            ->when($request->query('mode', 'overdue') === 'upcoming',
                fn($q) => $q->where('balance_due_date', '>=', Carbon::now()),
                fn($q) => $q->where('balance_due_date', '<',  Carbon::now())
            )
            ->whereYear('balance_due_date', $year)
            ->whereMonth('balance_due_date', $month)
            ->groupBy('due_date', 'created_by')
            ->orderBy('due_date');

        // Role-based admin filter
        if ($adminId) {
            $ids = is_array($adminId)
                ? $adminId
                : array_map('trim', explode(',', $adminId));
            $query->whereIn('created_by', $ids);
        }

        $rows = $query->with('createdBy:id,name')->get();

        // Collect all admin names up-front for consistent legend ordering
        $adminMap = $rows->keyBy('created_by')
            ->map(fn ($r) => optional($r->createdBy)->name ?? 'Unknown')
            ->all();

        // Build a date-keyed structure
        $byDate = [];
        foreach ($rows as $row) {
            $date       = $row->due_date;
            $adminName  = $adminMap[$row->created_by] ?? 'Unknown';

            if (!isset($byDate[$date])) {
                $byDate[$date] = [
                    'date'              => $date,
                    'date_label'        => Carbon::parse($date)->format('d M'),
                    'total_balance_due' => 0,
                    'total_count'       => 0,
                    'admins'            => [],
                ];
            }

            $byDate[$date]['total_balance_due'] += (float) $row->total_balance_due;
            $byDate[$date]['total_count']        += (int)   $row->total_count;
            $byDate[$date]['admins'][]            = [
                'name'        => $adminName,
                'admin_id'    => $row->created_by,
                'balance_due' => (float) $row->total_balance_due,
                'count'       => (int)   $row->total_count,
            ];
        }

        $days = array_values($byDate);

        $summary = [
            'total_balance_due' => collect($days)->sum('total_balance_due'),
            'total_count'       => collect($days)->sum('total_count'),
        ];

        return $this->success(
            compact('days', 'summary'),
            'Balance due overdue graph data retrieved successfully.',
        );
    }

    /**
     * GET /api/balance-due-over/list?date=2024-05-15&admin_id=1,2&page=1&per_page=10
     *
     * Returns paginated bookings for a specific due date (right-panel drill-down).
     */
    public function list(Request $request)
    {
        $date    = $request->query('date');     // YYYY-MM-DD
        $adminId = $request->query('admin_id');
        $perPage = (int) $request->query('per_page', 10);

        if (!$date || !Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid or missing date. Expected YYYY-MM-DD.',
            ], 400);
        }

        $query = Booking::query()
            ->where('payment_status', '!=', 'fully_paid')
            ->whereNotNull('balance_due_date')
            ->when($request->query('mode', 'overdue') === 'upcoming',
                fn($q) => $q->where('balance_due_date', '>=', Carbon::now()),
                fn($q) => $q->where('balance_due_date', '<',  Carbon::now())
            )
            ->whereDate('balance_due_date', $date)
            ->with(['customer:id,name', 'createdBy:id,name'])
            ->orderBy('balance_due_date', 'asc');

        if ($adminId) {
            $ids = is_array($adminId)
                ? $adminId
                : array_map('trim', explode(',', $adminId));
            $query->whereIn('created_by', $ids);
        }

        $paginated = $query->paginate($perPage);

        $data = $paginated->getCollection()->map(fn ($b) => [
            'id'               => $b->id,
            'booking_crm_id'   => $b->crm_id,
            'customer_name'    => optional($b->customer)->name ?? $b->customer_name ?? '—',
            'created_by_name'  => optional($b->createdBy)->name ?? '—',
            'payment_status'   => $b->payment_status,
            'booking_date'     => $b->booking_date
                                    ? Carbon::parse($b->booking_date)->format('d M Y')
                                    : null,
            'balance_due_date' => $b->balance_due_date
                                    ? Carbon::parse($b->balance_due_date)->format('d M Y')
                                    : null,
            'total_amount'     => (float) $b->total_amount,
            'paid_amount'      => (float) ($b->total_amount - $b->balance_due),
            'balance_due'      => (float) $b->balance_due,
        ]);

        return $this->success(
            [
                'data' => $data,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'total_page'   => $paginated->lastPage(),
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                ],
            ],
            'Overdue balance due bookings retrieved successfully.',
        );
    }

    /**
     * GET /api/balance-due-over  (original single-list endpoint — kept for ReceivableList.vue)
     * Filters: admin_id, date (MM-YYYY)
     */
    public function index(Request $request)
    {
        $admin = $request->query('admin_id');
        $date  = $request->query('date'); // MM-YYYY

        $query = Booking::query()
            ->where('payment_status', '!=', 'fully_paid')
            ->where('balance_due_date', '<', Carbon::now());

        if ($admin) {
            $adminIds = is_array($admin)
                ? $admin
                : array_map('trim', explode(',', (string) $admin));
            $query->whereIn('created_by', $adminIds);
        }

        if ($date) {
            if (!preg_match('/^(\d{2})-(\d{4})$/', $date, $m)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid date format. Expected MM-YYYY.',
                ], 400);
            }

            $month = (int) $m[1];
            $year  = (int) $m[2];

            if ($month < 1 || $month > 12) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid month. Month should be between 01–12.',
                ], 400);
            }

            $query->whereMonth('booking_date', $month)
                  ->whereYear('booking_date', $year);
        }

        $query
            ->withExists([
                'items as has_vantour' => fn ($q) =>
                    $q->where('product_type', 'App\\Models\\PrivateVanTour'),
            ])
            ->with(['customer', 'items', 'createdBy:id,name'])
            ->orderBy('has_vantour', 'asc')
            ->orderBy('balance_due_date', 'desc');

        $data = $query->get();

        return $this->success(
            UnpaidResource::collection($data),
            'Overdue balance due bookings retrieved successfully.',
        );
    }
}
