<?php

namespace App\Http\Controllers;

use App\Http\Resources\Accountance\UnpaidResource;
use App\Models\Booking;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BalanceDueOverController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $admin = $request->query('admin_id');
        $date = $request->query('date'); // Format: "01-2024" (MM-YYYY)

        $query = Booking::query(); // Use $query instead of $data for clarity

        $query->where('payment_status', '!=', 'fully_paid')
            ->where('balance_due_date', '<', Carbon::now());

        // Fix 1: Handle admin_id filter
        if ($admin) {
            if (is_array($admin)) {
                $query->whereIn('created_by', $admin);
            } else {
                $adminIds = is_string($admin) ? explode(',', $admin) : [$admin];
                $query->whereIn('created_by', array_map('trim', $adminIds));
            }
        }

        // Fix 2: Handle date filter BEFORE joins to avoid issues
        if ($date) {
            if (preg_match('/^(\d{2})-(\d{4})$/', $date, $matches)) {
                $month = (int)$matches[1];
                $year = (int)$matches[2];

                if ($month >= 1 && $month <= 12) {
                    $query->whereMonth('booking_date', $month)
                        ->whereYear('booking_date', $year);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'data' => null,
                        'message' => 'Invalid month. Month should be between 01-12.'
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'data' => null,
                    'message' => 'Invalid date format. Expected format: MM-YYYY (e.g., 01-2024)'
                ], 400);
            }
        }

        // Fix 3: Corrected VanTour sorting with proper relationship loading
        $query->withExists([
                'items as has_vantour' => function ($q) {
                    $q->where('product_type', 'App\\Models\\PrivateVanTour');
                }
            ])
            ->with(['customer', 'items']) // Fix 4: Add eager loading
            ->orderBy('has_vantour', 'asc')
            ->orderBy('balance_due_date', 'desc'); // Fix 5: Add secondary sorting

        // Fix 6: Execute query once and calculate totals
        $data = $query->get();

        $totalCount = $data->count();
        $totalAmount = $data->sum('balance_due');

        // Fix 7: Correct success method call with proper parameters
        return $this->success(
            UnpaidResource::collection($data),
            'Overdue balance due bookings retrieved successfully.',
        );
    }
}
