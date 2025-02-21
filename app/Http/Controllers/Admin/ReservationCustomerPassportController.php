<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationCustomerPassportResource;
use App\Models\ReservationCustomerPassport;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;

class ReservationCustomerPassportController extends Controller
{
    use ImageManager;
    use HttpResponses;

    public function index(string $booking_item_id)
    {
        try {
            $passports = ReservationCustomerPassport::where('booking_item_id', $booking_item_id)->get();

            return $this->success(ReservationCustomerPassportResource::collection($passports)
                ->additional([
                    'meta' => [
                        'total_page' => (int)ceil($passports->total() / $passports->perPage()),
                    ],
                ])
                ->response()
                ->getData(), 'File List');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function store(string $booking_item_id, Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'name' => 'nullable',
            'passport_number' => 'nullable',
            'dob' => 'nullable',
        ]);

        try {
            $fileData = $this->uploads($request->file(), 'files/');

            $passport = ReservationCustomerPassport::create([
                'booking_item_id' => $booking_item_id,
                'file' => $fileData['fileName'],
                'name' => $request->name,
                'passport_number' => $request->passport_number,
                'dob' => $request->dob,
            ]);

            return $this->success(new ReservationCustomerPassportResource($passport), 'File Created');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function update(string $booking_item_id, string $id, Request $request)
    {
        $request->validate([
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'name' => 'nullable',
            'passport_number' => 'nullable',
            'dob' => 'nullable',
        ]);

        try {
            $passport = ReservationCustomerPassport::find($id);

            if (!$passport) {
                return $this->error(null, 'File not found');
            }

            if ($request->file) {
                $fileData = $this->uploads($request->file(), 'files/');
            }

            $passport->update([
                'file' => $fileData['fileName'] ?? $passport->file,
                'name' => $request->name ?? $passport->name,
                'passport_number' => $request->passport_number ?? $passport->passport_number,
                'dob' => $request->dob ?? $passport->dob,
            ]);

            return $this->success(new ReservationCustomerPassportResource($passport), 'File Updated');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $booking_item_id, string $id)
    {
        try {
            $passport = ReservationCustomerPassport::find($id);

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
