<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingItemAmendmentResource;
use App\Models\BookingItemAmendment;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class AmendPartnerController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $limit          = $request->query('limit', 10);
        $bookingItemId  = $request->query('booking_item_id');
        $status         = $request->query('status');
        $userId         = $request->query('user_id');
        $daterange      = $request->query('daterange');
        $orderBy        = $request->query('order_by', 'created_at');
        $orderDirection = $request->query('order_direction', 'desc');
        $crmId          = $request->query('crm_id');         // NEW
        $amendStatus    = $request->query('amend_status');
        $productId      = $request->query('product_id');

        $query = BookingItemAmendment::with(['bookingItem.booking.customer', 'bookingItem.product']);

        if ($bookingItemId) {
            $query->where('booking_item_id', $bookingItemId);
        }

        if ($status) {
            $query->where('amend_status', $status);
        }
        if ($productId) {
            $query->where(function ($q) use ($productId) {
                $q->whereHas('bookingItem', function ($sub) use ($productId) {
                    $sub->where('product_id', $productId);
                })
                ->orWhere('item_snapshot', 'LIKE', '%"product_id":' . (int) $productId . '%');
            });
        }

        // Filter by user_id inside amend_history JSON
        if ($userId) {
            $query->whereJsonContains('amend_history', ['user_id' => (int) $userId]);
        }

        // Filter by created_at daterange
        if ($daterange) {
            $dates = explode(',', $daterange);
            if (count($dates) === 2) {
                $query->whereBetween('created_at', [
                    \Carbon\Carbon::parse(trim($dates[0]))->startOfDay(),
                    \Carbon\Carbon::parse(trim($dates[1]))->endOfDay(),
                ]);
            }
        }

        // NEW: Filter by amend_status (explicit dedicated param)
        if ($amendStatus) {
            $query->where('amend_status', $amendStatus);
        }

        // NEW: Filter by crm_id via bookingItem relationship
        if ($crmId) {
            $query->where(function ($q) use ($crmId) {
                // Search on live booking item relation
                $q->whereHas('bookingItem', function ($sub) use ($crmId) {
                    $sub->where('crm_id', 'LIKE', '%' . $crmId . '%');
                })
                // Also search in item_snapshot JSON for deleted booking items
                ->orWhere('item_snapshot', 'LIKE', '%' . $crmId . '%');
            });
        }

        // Sort
        $allowedOrderBy = ['created_at', 'updated_at', 'amend_status'];
        $allowedDirection = ['asc', 'desc'];

        $orderBy        = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'created_at';
        $orderDirection = in_array($orderDirection, $allowedDirection) ? $orderDirection : 'desc';

        $query->orderBy($orderBy, $orderDirection);

        $data = $query->paginate($limit);

        return $this->success(
            BookingItemAmendmentResource::collection($data)
                ->additional([
                    'meta' => [
                        'total_page' => (int) ceil($data->total() / $data->perPage()),
                    ],
                ])
                ->response()
                ->getData(),
            'Amendment List'
        );
    }

     public function show(string $id)
    {
        $amend = BookingItemAmendment::with(['bookingItem.booking.customer', 'bookingItem.product'])->find($id);
        if (!$amend) {
            return $this->error(null, 'Amendment not found', 404);
        }
        return $this->success(new BookingItemAmendmentResource($amend), 'Amendment details');
    }
}
