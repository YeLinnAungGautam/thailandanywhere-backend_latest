<?php
namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;

class ReportService
{
    public static function getSalesByAgent(string $daterange)
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
}
