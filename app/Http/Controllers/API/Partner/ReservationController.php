<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Models\BookingItemGroup;
use Illuminate\Http\Request;
use Exception;

class ReservationController extends Controller
{
    public function index (Request $request){
        $productIds = $request->productIds; // like 1,2,3
        $dateRange = $request->dateRange;
        $crmId = $request->crm_id;

        try {
            $query = BookingItemGroup::query()
            ->has('bookingItems')
            ->with([
                'booking',
                'booking.customer',
                'bookingItems',
                'bookingItems.product'
            ]);

            if ($productIds) {
                $productIds = is_string($request->productIds)
                    ? explode(',', $request->productIds)
                    : $request->productIds;

                $query->whereHas('bookingItems', function ($q) use ($productIds) {
                    $q->whereIn('product_id', $productIds);
                });
            }

            // Filter by CRM ID if provided
            if ($crmId) {
                $query->whereHas('booking', function ($q) use ($request) {
                    $q->where('crm_id', $request->crm_id);
                });
            }

            // Filter by date range if provided
            if ($dateRange) {
                $dates = explode(',', $dateRange);
                $dates = array_map('trim', $dates);
                $query->whereHas('bookingItems', function ($q) use ($dates) {
                    $q->whereBetween('service_date', $dates);
                });
            }

            return $this->success($query->get());
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }
}
