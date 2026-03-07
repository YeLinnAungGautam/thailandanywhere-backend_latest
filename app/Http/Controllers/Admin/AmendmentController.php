<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemAmendmentResource;
use App\Jobs\BookingVatJob;
use App\Jobs\UpdateBalanceDueDateJob;
use App\Jobs\UpdateBookingDatesJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingItemAmendment;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmendmentController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $limit         = $request->query('limit', 10);
        $bookingItemId = $request->query('booking_item_id');
        $status        = $request->query('status');

        $query = BookingItemAmendment::query();

        if ($bookingItemId) {
            $query->where('booking_item_id', $bookingItemId);
        }

        if ($status) {
            $query->where('amend_status', $status);
        }

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

    public function store(Request $request)
    {
        $request->validate([
            'booking_item_id' => 'required|exists:booking_items,id',
            'changes'         => 'required',
        ]);

        DB::beginTransaction();

        try {
            $bookingItem = BookingItem::findOrFail($request->booking_item_id);

            $changes = $request->changes;
            if (is_string($changes)) {
                $changes = json_decode($changes, true);
            }

            if (!is_array($changes)) {
                return $this->error(null, 'Changes must be a valid JSON array or object');
            }

            $cleanChanges = [];
            foreach ($changes as $key => $value) {
                if (!str_starts_with($key, 'current_')) {
                    $cleanChanges[$key] = $value;
                }
            }

            $amendment                  = new BookingItemAmendment();
            $amendment->booking_item_id = $request->booking_item_id;

            $amendHistory   = [];
            $amendHistory[] = [
                'timestamp'       => now()->toDateTimeString(),
                'changes'         => $cleanChanges,
                'previous_values' => array_filter($changes, function ($key) {
                    return str_starts_with($key, 'current_');
                }, ARRAY_FILTER_USE_KEY),
                'user_id'   => Auth::id() ?? null,
                'user_name' => Auth::user() ? Auth::user()->name : 'System',
            ];

            $amendment->amend_history   = $amendHistory;
            $amendment->amend_request   = true;
            $amendment->amend_mail_sent = false;
            $amendment->amend_approve   = false;
            $amendment->amend_status    = $request->input('amend_status', 'pending');
            $amendment->save();

            DB::commit();

            return $this->success(new BookingItemAmendmentResource($amendment), 'Amendment request saved successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->error(null, $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $amend = BookingItemAmendment::with('bookingItem')->find($id);
        if (!$amend) {
            return $this->error(null, 'Amendment not found', 404);
        }
        return $this->success(new BookingItemAmendmentResource($amend), 'Amendment details');
    }

    public function update(Request $request, string $id)
    {
        $amendment = BookingItemAmendment::find($id);

        if (!$amendment) {
            return $this->error(null, 'Amendment not found', 404);
        }

        DB::beginTransaction();

        try {
            if ($request->has('changes')) {
                $changes = $request->changes;
                if (is_string($changes)) {
                    $changes = json_decode($changes, true);
                }

                if (!is_array($changes)) {
                    return $this->error(null, 'Changes must be a valid JSON array or object');
                }

                $cleanChanges = [];
                foreach ($changes as $key => $value) {
                    if (!str_starts_with($key, 'current_')) {
                        $cleanChanges[$key] = $value;
                    }
                }

                $amendHistory   = $amendment->amend_history ?? [];
                $amendHistory[] = [
                    'timestamp'       => now()->toDateTimeString(),
                    'changes'         => $cleanChanges,
                    'previous_values' => array_filter($changes, function ($key) {
                        return str_starts_with($key, 'current_');
                    }, ARRAY_FILTER_USE_KEY),
                    'user_id'   => Auth::id() ?? null,
                    'user_name' => Auth::user() ? Auth::user()->name : 'System',
                ];

                $amendment->amend_history = $amendHistory;
            }

            if ($request->has('amend_request'))   $amendment->amend_request   = $request->amend_request;
            if ($request->has('amend_mail_sent')) $amendment->amend_mail_sent = $request->amend_mail_sent;
            if ($request->has('amend_approve'))   $amendment->amend_approve   = $request->amend_approve;
            if ($request->has('amend_status'))    $amendment->amend_status    = $request->amend_status;

            $amendment->save();

            DB::commit();

            return $this->success(new BookingItemAmendmentResource($amendment), 'Amendment updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        $amendment = BookingItemAmendment::find($id);

        if (!$amendment) {
            return $this->error(null, 'Amendment not found', 404);
        }

        $amendment->delete();

        return $this->success(null, 'Amendment deleted successfully');
    }

    public function rejectAmendment(string $id, Request $request)
    {
        $amendment = BookingItemAmendment::findOrFail($id);

        $amendment->amend_approve = false;
        $amendment->amend_status  = 'rejected';

        $amendHistory = $amendment->amend_history;
        $lastIndex    = count($amendHistory) - 1;

        if ($lastIndex >= 0) {
            $amendHistory[$lastIndex]['rejected_reason'] = $request->input('reason', 'No reason provided');
            $amendHistory[$lastIndex]['rejected_by']     = Auth::id();
            $amendHistory[$lastIndex]['rejected_at']     = now()->toDateTimeString();
            $amendment->amend_history                    = $amendHistory;
        }

        $amendment->save();

        return $this->success(
            new BookingItemAmendmentResource($amendment),
            'Amendment rejected successfully'
        );
    }

    public function approveAmendment(string $id)
    {
        $amendment = BookingItemAmendment::with('bookingItem.booking')->findOrFail($id);

        DB::beginTransaction();

        try {
            $bookingItem = $amendment->bookingItem;

            if (!$bookingItem) {
                return $this->error(null, 'Booking item not found', 404);
            }

            $booking = $bookingItem->booking;

            if (!$booking) {
                return $this->error(null, 'Booking not found', 404);
            }

            // Get latest changes from amend_history
            $amendHistory = $amendment->amend_history ?? [];
            $latestAmend  = end($amendHistory);
            $changes      = $latestAmend['changes'] ?? [];

            $isDeleteRequest = $changes['delete'] ?? false;

            if ($isDeleteRequest) {
                // ─── DELETE FLOW ──────────────────────────────────────

                // ✅ Save a full snapshot of the BookingItem BEFORE deletion
                // so the amendment record can still display what was deleted.
                $amendment->item_snapshot = $this->buildItemSnapshot($bookingItem, $booking);
                $amendment->save();

                $bookingItem->delete();

                // Recalculate only if NOT inclusive
                if (!$booking->is_inclusive) {
                    $this->recalculateBookingTotals($booking);
                }

            } else {
                // ─── UPDATE FLOW ──────────────────────────────────────
                $updateData = [];

                if (!empty($changes['service_date'])) {
                    $updateData['service_date'] = $changes['service_date'];
                }

                if (!empty($changes['checkout_date'])) {
                    if(!empty($changes['service_date'])){
                        $updateData['checkin_date'] = $changes['service_date'];
                    }
                    $updateData['checkout_date'] = $changes['checkout_date'];
                }

                if (isset($changes['quantity']) && $changes['quantity'] !== null) {
                    $updateData['quantity'] = $changes['quantity'];
                }

                if (isset($changes['selling_price']) && $changes['selling_price'] !== null) {
                    $updateData['selling_price'] = $changes['selling_price'];
                }

                if (isset($changes['cost_price']) && $changes['cost_price'] !== null) {
                    $updateData['cost_price'] = $changes['cost_price'];
                }

                if (!empty($changes['variation_id'])) {
                    $updateData['car_id'] = $changes['variation_id'];
                }

                if (isset($changes['total_amount']) && $changes['total_amount'] !== null) {
                    $updateData['amount'] = $changes['total_amount'];
                }

                if (isset($changes['total_cost_price']) && $changes['total_cost_price'] !== null) {
                    $updateData['total_cost_price'] = $changes['total_cost_price'];
                }

                if (isset($changes['child_quantity']) && $changes['child_quantity'] !== null) {
                    $individualPricing = $bookingItem->individual_pricing;

                    if (is_string($individualPricing)) {
                        $individualPricing = json_decode($individualPricing, true) ?? [];
                    }
                    if (!is_array($individualPricing)) {
                        $individualPricing = [];
                    }

                    $childQty = (int) $changes['child_quantity'];

                    // ✅ changes မှ အရင်ယူ၊ မပါလျှင် existing မှ fallback
                    $childPrice = (float) (
                        $changes['child_selling_price']
                        ?? $individualPricing['child']['selling_price']
                        ?? $individualPricing['child']['price']
                        ?? 0
                    );

                    $childCost = (float) (
                        $changes['child_cost_price']
                        ?? $individualPricing['child']['cost_price']
                        ?? 0
                    );

                    $individualPricing['child']['quantity']         = $childQty;
                    $individualPricing['child']['selling_price']    = $childPrice;  // ✅
                    $individualPricing['child']['cost_price']       = $childCost;   // ✅
                    $individualPricing['child']['amount']           = $childQty * $childPrice;
                    $individualPricing['child']['total_cost_price'] = $childQty * $childCost;

                    $updateData['individual_pricing'] = json_encode($individualPricing);
                }

                if (!empty($updateData)) {
                    $bookingItem->update($updateData);
                }

                if (!$booking->is_inclusive) {
                    $this->recalculateBookingTotals($booking);
                }
            }

            // Mark as approved
            $amendment->amend_approve = true;
            $amendment->amend_status  = 'completed';

            $lastIndex = count($amendHistory) - 1;
            if ($lastIndex >= 0) {
                $amendHistory[$lastIndex]['approved_by'] = Auth::id();
                $amendHistory[$lastIndex]['approved_at'] = now()->toDateTimeString();
                $amendment->amend_history                = $amendHistory;
            }

            $amendment->save();

            DB::commit();

            UpdateBalanceDueDateJob::dispatch($booking->id);
            BookingVatJob::dispatch($booking->id);
            UpdateBookingDatesJob::dispatch($booking->id);

            return $this->success(
                new BookingItemAmendmentResource($amendment),
                'Amendment approved successfully'
            );

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────────

    /**
     * Build a full snapshot of a BookingItem + its relations
     * so it can be preserved on the amendment record after the item is deleted.
     */
    private function buildItemSnapshot(BookingItem $item, ?Booking $booking): array
    {
        // ── Safe product name ─────────────────────────────────────────────────
        $productName = null;
        $productId   = $item->product_id;
        try {
            $productName = $item->product?->name ?? null;
        } catch (\Exception $e) {}

        // ── Decode all JSON string fields ─────────────────────────────────────
        $individualPricing = $item->individual_pricing;
        if (is_string($individualPricing)) {
            $individualPricing = json_decode($individualPricing, true) ?? null;
        }

        $variationSnapshot = $item->variation_snapshot;
        if (is_string($variationSnapshot)) {
            $variationSnapshot = json_decode($variationSnapshot, true) ?? null;
        }

        $productSnapshot = $item->product_snapshot;
        if (is_string($productSnapshot)) {
            $productSnapshot = json_decode($productSnapshot, true) ?? null;
        }

        $priceSnapshot = $item->price_snapshot;
        if (is_string($priceSnapshot)) {
            $priceSnapshot = json_decode($priceSnapshot, true) ?? null;
        }

        return [
            // ── Core item fields ──────────────────────────────────────────────
            'id'                  => $item->id,
            'booking_id'          => $item->booking_id,
            'crm_id'              => $item->crm_id,
            'product_type'        => $item->product_type,
            'product_id'          => $productId,
            'service_date'        => $item->service_date,
            'checkout_date'       => $item->checkout_date,
            'checkin_date'        => $item->checkin_date,
            'quantity'            => $item->quantity,
            'selling_price'       => $item->selling_price,
            'cost_price'          => $item->cost_price,
            'amount'              => $item->amount,
            'total_cost_price'    => $item->total_cost_price,
            'discount'            => $item->discount,
            'days'                => $item->days,
            'individual_pricing'  => $individualPricing,
            'item_name'           => $item->item_name ?? null,
            'comment'             => $item->comment ?? null,
            'special_request'     => $item->special_request ?? null,
            'pickup_location'     => $item->pickup_location ?? null,
            'pickup_time'         => $item->pickup_time ?? null,
            'route_plan'          => $item->route_plan ?? null,
            'cancellation'        => $item->cancellation ?? null,

            // ── Decoded snapshots (proper objects, not escaped strings) ────────
            'product_snapshot'    => $productSnapshot,
            'variation_snapshot'  => $variationSnapshot,
            'price_snapshot'      => $priceSnapshot,

            // ── Resolved relations ────────────────────────────────────────────
            'product' => $productName ? [
                'id'   => $productId,
                'name' => $productName,
            ] : null,

            'customer_info' => $booking?->customer ? [
                'id'   => $booking->customer->id,
                'name' => $booking->customer->name,
            ] : null,

            'booking' => $booking ? [
                'id'           => $booking->id,
                'crm_id'       => $booking->crm_id,
                'booking_date' => $booking->booking_date,
                'is_inclusive' => $booking->is_inclusive,
            ] : null,

            // ── Metadata ─────────────────────────────────────────────────────
            'snapshotted_at' => now()->toDateTimeString(),
        ];
    }

    private function recalculateBookingTotals(Booking $booking): void
    {
        $booking->refresh();

        $subTotal   = $booking->items->sum(fn ($item) => (float) ($item->amount ?? 0));
        $discount   = (float) ($booking->discount ?? 0);
        $grandTotal = $subTotal - $discount;
        $deposit    = (float) ($booking->deposit ?? 0);
        $balanceDue = $grandTotal - $deposit;

        $booking->update([
            'sub_total'   => $subTotal,
            'grand_total' => $grandTotal,
            'balance_due' => $balanceDue,
        ]);
    }
}
