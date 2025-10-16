<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageResource;
use App\Models\CashImage;
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
     * Create cash image and attach to multiple bookings via cash_imageables
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

            // Booking attachments
            'bookings' => 'required|array|min:1',
            'bookings.*.booking_id' => 'required|exists:bookings,id',
            'bookings.*.type' => 'nullable|string|max:255',
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
                'image_path' => $fileData['filePath'],
                'relatable_id' => 0, // Set to 0 since we're using cash_imageables
                'relatables' => null,
                'relatable_type' => $validated['relatable_type']
            ]);

            // Attach to multiple bookings via cash_imageables pivot table
            foreach ($validated['bookings'] as $bookingData) {
                $cashImage->cashBookings()->attach($bookingData['booking_id'], [
                    'type' => $bookingData['type'] ?? null,
                    'deposit' => $bookingData['deposit'] ?? 0,
                    'notes' => $bookingData['notes'] ?? null
                ]);
            }

            DB::commit();

            // Load relationships
            $cashImage->load('cashBookings.customer');

            return response()->json([
                'success' => true,
                'message' => 'Cash image created and attached to bookings successfully',
                'data' => [
                    'cash_image' => new CashImageResource($cashImage),
                    'attached_count' => count($validated['bookings'])
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
     * Update cash image data and sync booking attachments via cash_imageables
     */
    public function update(Request $request, String $id)
    {
        $cashImage = CashImage::find($id);

        if (!$cashImage) {
            return $this->error(null, 'Cash image not found', 404);
        }

        $validated = $request->validate([
            // Cash Image data
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240',
            'date' => 'sometimes|required|date_format:Y-m-d H:i:s',
            'sender' => 'sometimes|required|string|max:255',
            'receiver' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'interact_bank' => 'nullable|string|max:255',
            'currency' => 'sometimes|required|string|max:10',

            // Booking attachments
            'bookings' => 'nullable|array',
            'bookings.*.booking_id' => 'required|exists:bookings,id',
            'bookings.*.type' => 'nullable|string|max:255',
            'bookings.*.deposit' => 'nullable|numeric|min:0',
            'bookings.*.notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();

        try {
            // Update cash image data
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

            if (!empty($updateData)) {
                $cashImage->update($updateData);
            }

            // Sync booking attachments via cash_imageables
            if (isset($validated['bookings'])) {
                $syncData = [];

                foreach ($validated['bookings'] as $bookingData) {
                    $syncData[$bookingData['booking_id']] = [
                        'type' => $bookingData['type'] ?? null,
                        'deposit' => $bookingData['deposit'] ?? 0,
                        'notes' => $bookingData['notes'] ?? null
                    ];
                }

                // Sync will add new, update existing, and remove ones not in the array
                $cashImage->cashBookings()->sync($syncData);
            }

            DB::commit();

            // Load updated cash image with relationships
            $cashImage->load('cashBookings.customer');

            return response()->json([
                'success' => true,
                'message' => 'Cash image updated successfully',
                'data' => [
                    'cash_image' => new CashImageResource($cashImage),
                    'attached_count' => $cashImage->cashBookings->count()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error(null, 'Failed to update cash image: ' . $e->getMessage(), 500);
        }
    }
}
