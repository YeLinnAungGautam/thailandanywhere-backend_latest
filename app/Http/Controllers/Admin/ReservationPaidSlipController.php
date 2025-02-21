<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationPaidSlipResource;
use App\Models\ReservationPaidSlip;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;

class ReservationPaidSlipController extends Controller
{
    use ImageManager;
    use HttpResponses;

    public function index(string $booking_item_id)
    {
        try {
            $passports = ReservationPaidSlip::where('booking_item_id', $booking_item_id)->get();

            return $this->success(ReservationPaidSlipResource::collection($passports)
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
            'bank_name' => 'nullable',
            'date' => 'nullable',
            'is_corporate' => 'nullable',
            'comment' => 'nullable',
        ]);

        try {
            $fileData = $this->uploads($request->file, 'images/');

            $passport = ReservationPaidSlip::create([
                'booking_item_id' => $booking_item_id,
                'file' => $fileData['fileName'],
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'date' => $request->date,
                'is_corporate' => $request->is_corporate,
                'comment' => $request->comment,
            ]);

            return $this->success(new ReservationPaidSlipResource($passport), 'File Created');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function update(string $booking_item_id, string $id, Request $request)
    {
        $request->validate([
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'amount' => 'nullable',
            'bank_name' => 'nullable',
            'date' => 'nullable',
            'is_corporate' => 'nullable',
            'comment' => 'nullable',
        ]);

        try {
            $passport = ReservationPaidSlip::find($id);

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

            return $this->success(new ReservationPaidSlipResource($passport), 'File Updated');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $booking_item_id, string $id)
    {
        try {
            $passport = ReservationPaidSlip::find($id);

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
