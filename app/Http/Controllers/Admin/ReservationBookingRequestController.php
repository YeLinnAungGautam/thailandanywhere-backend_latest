<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use App\Models\ReservationBookingRequest;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;

class ReservationBookingRequestController extends Controller
{

    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(string $booking_item_id, Request $request)
    {

        $bookingItem = BookingItem::findOrFail($booking_item_id);

        $request->validate([
            'files' => 'required',
            'files.*' => 'file|mimes:jpeg,png,jpg,pdf,doc,docx|max:2048',
        ]);

        try {
            $savedFiles = [];

            // Handle multiple file uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $fileData = $this->uploads($file, 'files/');

                    // Create record for each file
                    $requestProve = new ReservationBookingRequest([
                        'booking_item_id' => $booking_item_id,
                        'file' => $fileData ? $fileData['fileName'] : 'no-file.jpg'
                    ]);

                    $requestProve->save();
                    $savedFiles[] = $requestProve;
                }
            }
            return $this->success(null, count($savedFiles) . ' booking request proves uploaded successfully');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $booking_item_id, string $id)
    {
        try {
            $passport = ReservationBookingRequest::find($id);

            if (!$passport) {
                return $this->error(null, 'File not found');
            }

            $passport->delete();

            return $this->success(null, 'File Deleted');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }
}
