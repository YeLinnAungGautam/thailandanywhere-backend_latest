<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationReceiptImageResource;
use App\Models\ReservationExpenseReceipt;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;

class ReservationExpenseReceiptController extends Controller
{

    use ImageManager;
    use HttpResponses;
    /**
     * Display a listing of the resource.
     */
    public function index(string $booking_item_id)
    {
        try {
            $receipt = ReservationExpenseReceipt::where('booking_item_id', $booking_item_id)->get();

            return $this->success(ReservationReceiptImageResource::collection($receipt)
                ->additional([
                    'meta' => [
                        'total_page' => (int)ceil($receipt->total() / $receipt->perPage()),
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

            $receipt = ReservationExpenseReceipt::create([
                'booking_item_id' => $booking_item_id,
                'file' => $fileData['fileName'],
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'date' => $request->date,
                'is_corporate' => $request->is_corporate,
                'comment' => $request->comment,
            ]);

            return $this->success(new ReservationReceiptImageResource($receipt), 'File Created');
        } catch (Exception $e) {
            // Return a JSON response with the error message
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
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
            $receipt = ReservationExpenseReceipt::find($id);

            if (!$receipt) {
                return $this->error(null, 'File not found');
            }

            if ($request->hasFile('file')) {
                $fileData = $this->uploads($request->file, 'images/');
            }

            $receipt->update([
                'file' => $fileData['fileName'] ?? $receipt->file,
                'amount' => $request->amount ?? $receipt->amount,
                'bank_name' => $request->bank_name ?? $receipt->bank_name,
                'date' => $request->date ?? $receipt->date,
                'is_corporate' => $request->is_corporate ?? $receipt->is_corporate,
                'comment' => $request->comment ?? $receipt->comment,
            ]);

            return $this->success(new ReservationReceiptImageResource($receipt), 'File Updated');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $booking_item_id, string $id)
    {
        try {
            $passport = ReservationExpenseReceipt::find($id);

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
