<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashImageBookingResource;
use App\Models\CashImageBooking;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class CashImageBookingController extends Controller
{
    use HttpResponses;
    public function index(Request $request)
    {
        $query = CashImageBooking::with(['cashImage', 'booking']);

        // Filter by cash_image_id if provided
        if ($request->has('cash_image_id')) {
            $query->where('cash_image_id', $request->cash_image_id);
        }

        // Filter by booking_id if provided
        if ($request->has('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        $attachments = $query->get();

        return $this->success(CashImageBookingResource::collection($attachments), 'Attachments retrieved successfully');
    }

    /**
     * Store a newly created cash image booking attachment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cash_image_id' => 'required|exists:cash_images,id',
            'booking_id' => 'required|exists:bookings,id',
            'deposit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        // Check if attachment already exists
        $existing = CashImageBooking::where('cash_image_id', $validated['cash_image_id'])
            ->where('booking_id', $validated['booking_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This cash image is already attached to this booking',
                'data' => $existing
            ], 409);
        }

        $attachment = CashImageBooking::create([
            'cash_image_id' => $validated['cash_image_id'],
            'booking_id' => $validated['booking_id'],
            'deposit' => $validated['deposit'] ?? 0,
            'notes' => $validated['notes']
        ]);

        $attachment->load(['cashImage', 'booking']);

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Cash image attached to booking successfully',
        //     'data' => $attachment
        // ], 201);

        return $this->success(new CashImageBookingResource($attachment), 'Cash image attached to booking successfully');
    }

    /**
     * Display the specified cash image booking attachment
     */
    public function show($id)
    {
        $attachment = CashImageBooking::with(['cashImage', 'booking'])->find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found'
            ], 404);
        }

        // return response()->json([
        //     'success' => true,
        //     'data' => $attachment
        // ]);

        return $this->success(new CashImageBookingResource($attachment), 'Attachment retrieved successfully');
    }

    /**
     * Update the specified cash image booking attachment
     */
    public function update(Request $request, $id)
    {
        $attachment = CashImageBooking::find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found'
            ], 404);
        }

        $validated = $request->validate([
            'cash_image_id' => 'sometimes|exists:cash_images,id',
            'booking_id' => 'sometimes|exists:bookings,id',
            'deposit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        // If updating cash_image_id or booking_id, check for duplicates
        if (isset($validated['cash_image_id']) || isset($validated['booking_id'])) {
            $cashImageId = $validated['cash_image_id'] ?? $attachment->cash_image_id;
            $bookingId = $validated['booking_id'] ?? $attachment->booking_id;

            $existing = CashImageBooking::where('cash_image_id', $cashImageId)
                ->where('booking_id', $bookingId)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This cash image is already attached to this booking'
                ], 409);
            }
        }

        $attachment->update($validated);
        $attachment->load(['cashImage', 'booking']);

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Attachment updated successfully',
        //     'data' => $attachment
        // ]);

        return $this->success(new CashImageBookingResource($attachment), 'Attachment updated successfully');
    }

    /**
     * Remove the specified cash image booking attachment
     */
    public function destroy($id)
    {
        $attachment = CashImageBooking::find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found'
            ], 404);
        }

        $attachment->delete();

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Attachment deleted successfully'
        // ]);

        return $this->success(null, 'Attachment deleted successfully');
    }

    /**
     * Bulk create attachments
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'attachments' => 'required|array',
            'attachments.*.cash_image_id' => 'required|exists:cash_images,id',
            'attachments.*.booking_id' => 'required|exists:bookings,id',
            'attachments.*.deposit' => 'nullable|numeric|min:0',
            'attachments.*.notes' => 'nullable|string'
        ]);

        $created = [];
        $errors = [];

        foreach ($validated['attachments'] as $index => $attachmentData) {
            // Check if attachment already exists
            $existing = CashImageBooking::where('cash_image_id', $attachmentData['cash_image_id'])
                ->where('booking_id', $attachmentData['booking_id'])
                ->first();

            if ($existing) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Cash image ' . $attachmentData['cash_image_id'] . ' is already attached to booking ' . $attachmentData['booking_id']
                ];
                continue;
            }

            $attachment = CashImageBooking::create([
                'cash_image_id' => $attachmentData['cash_image_id'],
                'booking_id' => $attachmentData['booking_id'],
                'deposit' => $attachmentData['deposit'] ?? 0,
                'notes' => $attachmentData['notes'] ?? null
            ]);

            $attachment->load(['cashImage', 'booking']);
            $created[] = $attachment;
        }

        // return response()->json([
        //     'success' => true,
        //     'message' => count($created) . ' attachments created successfully',
        //     'data' => $created,
        //     'errors' => $errors
        // ], 201);

        return $this->success(CashImageBookingResource::collection($created), 'Attachments created successfully');
    }

    /**
     * Bulk delete attachments
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:cash_image_bookings,id'
        ]);

        CashImageBooking::whereIn('id', $validated['ids'])->delete();

        // return response()->json([
        //     'success' => true,
        //     'message' => $deleted . ' attachments deleted successfully'
        // ]);

        return $this->success(null, 'Attachments deleted successfully');
    }
}
