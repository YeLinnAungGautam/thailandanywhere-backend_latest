<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingItemGroupRequest;
use App\Http\Resources\BookingItemGroupResource;
use App\Models\Booking;
use App\Models\BookingItemGroup;
use App\Traits\HttpResponses;
use Exception;

class BookingItemGroupController extends Controller
{
    use HttpResponses;

    public function index(Booking $booking)
    {
        $groups = $booking->bookingItemGroups()
            ->with('bookingItems', 'booking')
            ->get();

        return $this->success(BookingItemGroupResource::collection($groups)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($groups->total() / $groups->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Group List');
    }

    public function update(Booking $booking, BookingItemGroup $booking_item_group, BookingItemGroupRequest $request)
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('booking_request_proof')) {
                $booking_request_proof_file = upload_file($request->booking_request_proof, 'booking_item_groups/');

                $data['booking_request_proof'] = $booking_request_proof_file['fileName'];
            }

            if ($request->hasFile('expense_mail_proof')) {
                $expense_mail_proof_file = upload_file($request->expense_mail_proof, 'booking_item_groups/');

                $data['expense_mail_proof'] = $expense_mail_proof_file['fileName'];
            }

            if ($request->hasFile('confirmation_image')) {
                $confirmation_image_file = upload_file($request->confirmation_image, 'booking_item_groups/');

                $data['confirmation_image'] = $confirmation_image_file['fileName'];
            }

            $booking_item_group->update($data);

            return $this->success(new BookingItemGroupResource($booking_item_group), 'Booking Item Group updated successfully');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
