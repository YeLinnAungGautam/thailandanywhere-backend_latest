<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationBookingConfirmLetterResource;
use App\Models\ReservationBookingConfirmLetter;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;

class BookingConfirmLetterController extends Controller
{
    use ImageManager;
    use HttpResponses;

    public function index(string $booking_item_id)
    {
        try {
            $passports = ReservationBookingConfirmLetter::where('reservation_id', $booking_item_id)->get();

            return $this->success(ReservationBookingConfirmLetterResource::collection($passports)
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
            'amount' => 'nullable',
            'invoice' => 'nullable',
            'due_date' => 'nullable',
            'customer' => 'nullable',
            'sender_name' => 'nullable',
        ]);

        try {
            $fileData = $this->uploads($request->file, 'images/');

            $passport = ReservationBookingConfirmLetter::create([
                'booking_item_id' => $booking_item_id,
                'file' => $fileData['fileName'],
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'date' => $request->date,
                'is_corporate' => $request->is_corporate,
                'comment' => $request->comment,
            ]);

            return $this->success(new ReservationBookingConfirmLetterResource($passport), 'File Created');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function update(string $booking_item_id, string $id, Request $request)
    {
        $request->validate([
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'amount' => 'nullable',
            'invoice' => 'nullable',
            'due_date' => 'nullable',
            'customer' => 'nullable',
            'sender_name' => 'nullable',
        ]);

        try {
            $passport = ReservationBookingConfirmLetter::find($id);

            if (!$passport) {
                return $this->error(null, 'File not found');
            }

            if ($request->hasFile('file')) {
                $fileData = $this->uploads($request->file, 'images/');
            }

            $passport->update([
                'file' => $fileData['fileName'] ?? $passport->file,
                'amount' => $request->amount ?? $passport->amount,
                'bank_name' => $request->bank_name ?? $passport->bank_name,
                'date' => $request->date ?? $passport->date,
                'is_corporate' => $request->is_corporate ?? $passport->is_corporate,
                'comment' => $request->comment ?? $passport->comment,
            ]);

            return $this->success(new ReservationBookingConfirmLetterResource($passport), 'File Updated');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $booking_item_id, string $id)
    {
        try {
            $passport = ReservationBookingConfirmLetter::find($id);

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
