<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemAmendmentResource;
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
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $bookingItemId = $request->query('booking_item_id');
        $status = $request->query('status');

        $query = BookingItemAmendment::query()->with('bookingItem');

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
                        'total_page' => (int)ceil($data->total() / $data->perPage()),
                    ],
                ])
                ->response()
                ->getData(),
            'Amendment List'
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'booking_item_id' => 'required|exists:booking_items,id',
            'changes' => 'required',
        ]);

        DB::beginTransaction();

        try {
            // Find the booking item
            $bookingItem = BookingItem::findOrFail($request->booking_item_id);

            // Get changes data which might be JSON string or array
            $changes = $request->changes;
            if (is_string($changes)) {
                $changes = json_decode($changes, true);
            }

            // Validate changes format
            if (!is_array($changes)) {
                return $this->error(null, 'Changes must be a valid JSON array or object');
            }

            // Clean up the changes to only include the actual changes
            $cleanChanges = [];
            foreach ($changes as $key => $value) {
                // Skip keys that start with 'current_' since they are just for reference
                if (!str_starts_with($key, 'current_')) {
                    $cleanChanges[$key] = $value;
                }
            }

            // Create a new amendment record
            $amendment = new BookingItemAmendment();
            $amendment->booking_item_id = $request->booking_item_id; // Store the booking_item_id

            // Initialize amendment history
            $amendHistory = [];

            // Add new amendment request to history
            $amendHistory[] = [
                'timestamp' => now()->toDateTimeString(),
                'changes' => $cleanChanges,
                'previous_values' => array_filter($changes, function($key) {
                    return str_starts_with($key, 'current_');
                }, ARRAY_FILTER_USE_KEY),
                'user_id' => Auth::id() ?? null,
                'user_name' => Auth::user() ? Auth::user()->name : 'System',
            ];

            // Set the amendment record properties
            $amendment->amend_history = $amendHistory;
            $amendment->amend_request = true;
            $amendment->amend_mail_sent = false;
            $amendment->amend_approve = false;
            $amendment->amend_status = $request->input('amend_status', 'pending');
            $amendment->save();

            DB::commit();

            return $this->success(new BookingItemAmendmentResource($amendment), 'Amendment request saved successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $amend = BookingItemAmendment::with('bookingItem')->find($id);
        if (!$amend) {
            return $this->error(null, 'Amendment not found', 404);
        }
        return $this->success(new BookingItemAmendmentResource($amend), 'Amendment details');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $amendment = BookingItemAmendment::find($id);

        if (!$amendment) {
            return $this->error(null, 'Amendment not found', 404);
        }

        DB::beginTransaction();

        try {
            if ($request->has('changes')) {
                // Get changes data which might be JSON string or array
                $changes = $request->changes;
                if (is_string($changes)) {
                    $changes = json_decode($changes, true);
                }

                // Validate changes format
                if (!is_array($changes)) {
                    return $this->error(null, 'Changes must be a valid JSON array or object');
                }

                // Clean up the changes to only include the actual changes
                $cleanChanges = [];
                foreach ($changes as $key => $value) {
                    // Skip keys that start with 'current_' since they are just for reference
                    if (!str_starts_with($key, 'current_')) {
                        $cleanChanges[$key] = $value;
                    }
                }

                // Get the current amendment history
                $amendHistory = $amendment->amend_history ?? [];

                // Add new amendment request to history
                $amendHistory[] = [
                    'timestamp' => now()->toDateTimeString(),
                    'changes' => $cleanChanges,
                    'previous_values' => array_filter($changes, function($key) {
                        return str_starts_with($key, 'current_');
                    }, ARRAY_FILTER_USE_KEY),
                    'user_id' => Auth::id() ?? null,
                    'user_name' => Auth::user() ? Auth::user()->name : 'System',
                ];

                $amendment->amend_history = $amendHistory;
            }

            // Update other fields if provided
            if ($request->has('amend_request')) {
                $amendment->amend_request = $request->amend_request;
            }

            if ($request->has('amend_mail_sent')) {
                $amendment->amend_mail_sent = $request->amend_mail_sent;
            }

            if ($request->has('amend_approve')) {
                $amendment->amend_approve = $request->amend_approve;
            }

            if ($request->has('amend_status')) {
                $amendment->amend_status = $request->amend_status;
            }

            $amendment->save();

            DB::commit();

            return $this->success(new BookingItemAmendmentResource($amendment), 'Amendment updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
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
        $amendment->amend_status = 'rejected';

        // Add rejection reason to the last amendment in history
        $amendHistory = $amendment->amend_history;
        $lastIndex = count($amendHistory) - 1;

        if ($lastIndex >= 0) {
            $amendHistory[$lastIndex]['rejected_reason'] = $request->input('reason', 'No reason provided');
            $amendHistory[$lastIndex]['rejected_by'] = Auth::id();
            $amendHistory[$lastIndex]['rejected_at'] = now()->toDateTimeString();

            $amendment->amend_history = $amendHistory;
        }

        $amendment->save();

        return $this->success(
            new BookingItemAmendmentResource($amendment),
            'Amendment rejected successfully'
        );
    }
}
