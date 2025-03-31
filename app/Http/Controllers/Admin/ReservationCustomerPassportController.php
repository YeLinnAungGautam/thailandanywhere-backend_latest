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
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'name' => 'required',
            'passport_number' => 'nullable',
            'dob' => 'nullable',
        ]);

        try {
            $fileData = null;
            if ($request->hasFile('file')) {
                $fileData = $this->uploads($request->file, 'passport/');
            }

            $passport = ReservationCustomerPassport::create([
                'booking_item_id' => $booking_item_id,
                'file' => $fileData ? $fileData['fileName'] : 'no-file.jpg',
                'name' => $request->name,
                'passport_number' => $request->passport_number ?? '-',
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
            'name' => 'required',
            'passport_number' => 'nullable',
            'dob' => 'nullable',
        ]);

        try {
            $passport = ReservationCustomerPassport::find($id);

            if (!$passport) {
                return $this->error(null, 'File not found');
            }

            $updateData = [
                'name' => $request->name,
            ];

            if ($request->hasFile('file')) {
                $fileData = $this->uploads($request->file, 'files/');
                $updateData['file'] = $fileData['fileName'];
            }

            if ($request->has('passport_number')) {
                $updateData['passport_number'] = $request->passport_number ?: '-';
            }

            if ($request->has('dob')) {
                $updateData['dob'] = $request->dob ? $request->dob : null;
            }

            $passport->update($updateData);

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
