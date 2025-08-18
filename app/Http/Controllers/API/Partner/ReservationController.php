<?php
namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItem\BookingItemGroupDetailResource;
use App\Http\Resources\BookingItem\BookingItemGroupListResource;
use App\Models\BookingItemGroup;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Exception;

class ReservationController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        try {
            $query = BookingItemGroup::query()
                ->has('bookingItems')
                ->with([
                    'booking',
                    'booking.customer',
                    'bookingItems',
                    'bookingItems.product'
                ]);

            // Product ID filter
            $this->applyProductFilter($query, $request);

            // CRM ID filter
            $this->applyCrmFilter($query, $request);

            // Date Range filter
            $this->applyDateRangeFilter($query, $request);

            $bookingItemGroups = $query->paginate($request->limit ?? 20);

            return $this->success(
                BookingItemGroupListResource::collection($bookingItemGroups)->additional([
                    'meta' => [
                        'total_page' => (int)ceil($bookingItemGroups->total() / $bookingItemGroups->perPage()),
                    ],
                ])
                ->response()
                ->getData(),
                'Booking Item Groups'
            );

        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    private function applyProductFilter($query, $request)
    {
        if (!$request->productIds && !$request->productId) {
            return;
        }

        // Handle productIds parameter (comma-separated string or array)
        $productIds = [];

        if ($request->productIds) {
            $productIds = is_array($request->productIds)
                ? $request->productIds
                : explode(',', $request->productIds);
        } elseif ($request->productId) {
            $productIds = is_array($request->productId)
                ? $request->productId
                : explode(',', $request->productId);
        }

        // Clean up the array
        $productIds = array_filter(array_map('trim', $productIds));

        if (!empty($productIds)) {
            $query->whereHas('bookingItems', function ($q) use ($productIds, $request) {
                $q->whereIn('product_id', $productIds);

                // Add product type filter if specified
                if ($request->productType) {
                    $q->where('product_type', $request->productType);
                }
            });
        }
    }

    private function applyCrmFilter($query, $request)
    {
        if ($request->crm_id) {
            $query->whereHas('booking', function ($q) use ($request) {
                $q->where('crm_id', $request->crm_id);
            });
        }
    }

    private function applyDateRangeFilter($query, $request)
    {
        if (!$request->dateRange) {
            return;
        }

        $dates = array_map('trim', explode(',', $request->dateRange));

        if (count($dates) === 2) {
            $query->whereHas('bookingItems', function ($q) use ($dates) {
                $q->whereBetween('service_date', $dates);
            });
        }
    }

    public function detail(BookingItemGroup $booking_item_group)
    {
        try {
            $booking_item_group->load([
                'booking',
                'bookingItems',
                'bookingItems.product',
                'customerDocuments',
                'cashImages',
            ]);

            return $this->success(
                new BookingItemGroupDetailResource($booking_item_group),
                'Booking Item Group Detail'
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
