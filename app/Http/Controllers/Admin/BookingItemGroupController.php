<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingItemGroupRequest;
use App\Http\Resources\BookingItemGroupResource;
use App\Models\Booking;
use App\Models\BookingItemGroup;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;

class BookingItemGroupController extends Controller
{
    use HttpResponses;

    public function index(Booking $booking)
    {
        $groups = $booking->bookingItemGroups()
            ->with('bookingItems', 'booking')
            ->paginate(10);

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

    public function storePassports(BookingItemGroup $booking_item_group, Request $request)
    {
        $request->validate([
            'passports' => 'required|array',
            'passports.*.file' => 'required|mimes:jpg,jpeg,png,pdf|max:2048',
            'passports.*.name' => 'nullable|string|max:255',
            'passports.*.passport_no' => 'nullable|string|max:255',
            'passports.*.dob' => 'nullable|date_format:Y-m-d',
            'passports.*.expiry_date' => 'nullable|date_format:Y-m-d',
            'passports.*.place_of_issue' => 'nullable|string|max:255',
            'passports.*.country_of_issue' => 'nullable|string|max:255',
        ]);

        try {
            foreach ($request->passports as $passport) {
                $passport_file = upload_file($passport->file, 'booking_item_groups/passports/');

                $booking_item_group->customerDocuments()->create([
                    'type' => 'passport',
                    'file' => $passport_file['fileName'],
                    'file_name' => $passport_file['filePath'],
                    'mime_type' => $passport_file['fileType'],
                    'file_size' => $passport_file['fileSize'],
                ]);
            }

            return $this->success(null, 'Passport uploaded successfully');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
