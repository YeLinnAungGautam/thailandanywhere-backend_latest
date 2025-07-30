<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageResource;
use App\Http\Resources\CashImageBookingResource;
use App\Models\CashImage;
use App\Models\CashImageBooking;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CashImageBookingController extends Controller
{
    use HttpResponses;
    use ImageManager;

    /**
     * Create cash image and attach to multiple bookings
     */
    public function createAndAttach(Request $request)
    {
        $validated = $request->validate([
            // Cash Image data
            'image' => 'required',
            'date' => 'required|date_format:Y-m-d H:i:s',
            'sender' => 'required|string|max:255',
            'receiver' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'interact_bank' => 'nullable|string|max:255',
            'currency' => 'required|string|max:10',
            'relatable_type' => 'required|string',
            'relatable_id' => 'required|integer',

            // Booking attachments
            'bookings' => 'required|array|min:1',
            'bookings.*.booking_id' => 'required|exists:bookings,id',
            'bookings.*.deposit' => 'nullable|numeric|min:0',
            'bookings.*.notes' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            // Create cash image
            $fileData = $this->uploads($validated['image'], 'images/');

            $cashImage = CashImage::create([
                'image' => $fileData['fileName'],
                'date' => $validated['date'],
                'sender' => $validated['sender'],
                'receiver' => $validated['receiver'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'interact_bank' => $validated['interact_bank'] ?? null,
                'relatable_type' => $validated['relatable_type'],
                'relatable_id' => $validated['relatable_id'],
                'image_path' => $fileData['filePath'],
            ]);

            // Attach to multiple bookings
            $attachments = [];
            foreach ($validated['bookings'] as $bookingData) {
                $attachment = CashImageBooking::create([
                    'cash_image_id' => $cashImage->id,
                    'booking_id' => $bookingData['booking_id'],
                    'deposit' => $bookingData['deposit'] ?? 0,
                    'notes' => $bookingData['notes'] ?? null
                ]);

                $attachment->load(['cashImage', 'booking']);
                $attachments[] = $attachment;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cash image created and attached to bookings successfully',
                'data' => [
                    'cash_image' => new CashImageResource($cashImage),
                    'attachments' => CashImageBookingResource::collection($attachments),
                    'attached_count' => count($attachments)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists
            if (isset($fileData['fileName'])) {
                Storage::delete('images/' . $fileData['fileName']);
            }

            return $this->error(null, 'Failed to create cash image and attachments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update cash image data and sync booking attachments
     */
    public function update(Request $request, String $id)
    {
        $cashImage = CashImage::find($id);

        if (!$cashImage) {
            return $this->error(null, 'Cash image not found', 404);
        }

        // Handle both FormData and JSON requests
        $validated = $request->validate([
            // Cash Image data (image is optional for updates)
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240',
            'date' => 'sometimes|required|date_format:Y-m-d H:i:s',
            'sender' => 'sometimes|required|string|max:255',
            'receiver' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'interact_bank' => 'nullable|string|max:255',
            'currency' => 'sometimes|required|string|max:10',

            // Booking attachments
            'bookings' => 'nullable|array',
            'bookings.*.id' => 'nullable|exists:cash_image_bookings,id',
            'bookings.*.booking_id' => 'required|exists:bookings,id',
            'bookings.*.deposit' => 'nullable|numeric|min:0',
            'bookings.*.notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();

        try {
            // Update cash image data - only update provided fields
            $updateData = [];

            if (isset($validated['date'])) {
                $updateData['date'] = $validated['date'];
            }
            if (isset($validated['sender'])) {
                $updateData['sender'] = $validated['sender'];
            }
            if (isset($validated['receiver'])) {
                $updateData['receiver'] = $validated['receiver'];
            }
            if (isset($validated['amount'])) {
                $updateData['amount'] = $validated['amount'];
            }
            if (isset($validated['currency'])) {
                $updateData['currency'] = $validated['currency'];
            }
            if (array_key_exists('interact_bank', $validated)) {
                $updateData['interact_bank'] = $validated['interact_bank'];
            }

            // Update cash image if there are fields to update
            if (!empty($updateData)) {
                $cashImage->update($updateData);
            }

            // Handle booking attachments sync
            $currentAttachments = CashImageBooking::where('cash_image_id', $id)->get();
            $providedBookings = $validated['bookings'] ?? [];

            // Remove attachments that are no longer in the provided list
            foreach ($currentAttachments as $current) {
                $isInProvided = collect($providedBookings)->contains(function ($provided) use ($current) {
                    return (isset($provided['id']) && $provided['id'] == $current->id) ||
                           (!isset($provided['id']) && $provided['booking_id'] == $current->booking_id);
                });

                if (!$isInProvided) {
                    $current->delete();
                }
            }

            // Process provided bookings
            $attachments = [];
            foreach ($providedBookings as $bookingData) {
                if (isset($bookingData['id']) && $bookingData['id']) {
                    // Update existing attachment
                    $attachment = CashImageBooking::find($bookingData['id']);
                    if ($attachment && $attachment->cash_image_id == $id) {
                        $attachment->update([
                            'booking_id' => $bookingData['booking_id'],
                            'deposit' => $bookingData['deposit'] ?? 0,
                            'notes' => $bookingData['notes'] ?? null
                        ]);
                        $attachment->load(['cashImage', 'booking.customer']);
                        $attachments[] = $attachment;
                    }
                } else {
                    // Create new attachment
                    $attachment = CashImageBooking::create([
                        'cash_image_id' => $id,
                        'booking_id' => $bookingData['booking_id'],
                        'deposit' => $bookingData['deposit'] ?? 0,
                        'notes' => $bookingData['notes'] ?? null
                    ]);
                    $attachment->load(['cashImage', 'booking.customer']);
                    $attachments[] = $attachment;
                }
            }

            DB::commit();

            // Load updated cash image with relationships
            $cashImage->load(['bookings.customer']);

            return response()->json([
                'success' => true,
                'message' => 'Cash image updated successfully',
                'data' => [
                    'cash_image' => $cashImage,
                    'attachments' => CashImageBookingResource::collection($attachments),
                    'attached_count' => count($attachments)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists and there was an error
            if (isset($fileData['fileName'])) {
                Storage::delete('images/' . $fileData['fileName']);
            }

            return $this->error(null, 'Failed to update cash image: ' . $e->getMessage(), 500);
        }
    }
}
