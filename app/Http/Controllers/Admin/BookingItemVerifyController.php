<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingItemVerifyController extends Controller
{
    use HttpResponses;

    public function updateVerifyStatus(Request $request, $id)
    {
        // Find the booking item
        $booking = Booking::find($id);

        if (!$booking) {
            return $this->error(['id' => 'Booking item not found.'], 'Validation failed.', 404);
        }

        // Validate only the verify_status field
        $validator = Validator::make($request->all(), [
            'verify_status' => 'required|in:verified,unverified,pending'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed.', 422);
        }

        // Update ONLY the verify_status field
        $booking->update([
            'verify_status' => $request->verify_status
        ]);

        // Return the updated booking item
        return $this->success(new BookingResource($booking), 'Verification status updated successfully.');
    }
}
