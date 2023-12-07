<?php
namespace App\Services;

use App\Models\Admin;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SaleReportService
{
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
        $date = $date ?? Carbon::now()->subDays(2)->format('Y-m-d');

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
}
