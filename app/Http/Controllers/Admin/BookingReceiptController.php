<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingReceiptResource;
use App\Models\BookingReceipt;
use App\Models\ReservationExpenseReceipt;
use App\Services\ReceiptService;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookingReceiptController extends Controller
{
    use ImageManager;
    use HttpResponses;

    protected $receiptService;

    public function __construct(ReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    public function getall(Request $request)
    {
        $result = $this->receiptService->getall($request);

        if ($result['success']) {
            return response()->json($result, 200);
        } else {
            return response()->json($result, 500);
        }
    }

    public function index(string $booking_id)
    {
        try {
            $passports = BookingReceipt::where('booking_id', $booking_id)->get();

            return $this->success(BookingReceiptResource::collection($passports)
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



    public function store(string $booking_id, Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'amount' => 'nullable',
            'bank_name' => 'nullable',
            'date' => 'nullable',
            'is_corporate' => 'nullable',
            'note' => 'nullable',
            'sender' => 'nullable',
            'reciever' => 'nullable',
            'interact_bank' => 'nullable',
        ]);

        try {
            $fileData = $this->uploads($request->file, 'images/');

            $passport = BookingReceipt::create([
                'booking_id' => $booking_id,
                'image' => $fileData['fileName'],
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'date' => $request->date,
                'is_corporate' => $request->is_corporate,
                'note' => $request->note,
                'sender' => $request->sender,
                'reciever' => $request->reciever,
                'interact_bank' => $request->interact_bank ?? 'personal',
            ]);

            return $this->success(new BookingReceiptResource($passport), 'File Created');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function update(string $booking_id, string $id, Request $request)
    {
        $request->validate([
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'amount' => 'nullable',
            'bank_name' => 'nullable',
            'date' => 'nullable',
            'is_corporate' => 'nullable',
            'note' => 'nullable',
            'sender' => 'nullable',
            'reciever' => 'nullable',
            'interact_bank' => 'nullable',
        ]);

        try {
            $passport = BookingReceipt::find($id);

            if (!$passport) {
                return $this->error(null, 'File not found');
            }

            if ($request->hasFile('file')) {
                $fileData = $this->uploads($request->file, 'images/');
            }

            $passport->update([
                'image' => $fileData['fileName'] ?? $passport->image,
                'amount' => $request->amount ?? $passport->amount,
                'bank_name' => $request->bank_name ?? $passport->bank_name,
                'date' => $request->date ?? $passport->date,
                'is_corporate' => $request->is_corporate ?? $passport->is_corporate,
                'note' => $request->note ?? $passport->note,
                'sender' => $request->sender ?? $passport->sender,
                'reciever' => $request->reciever ?? $passport->reciever,
                'interact_bank' => $request->interact_bank ?? $passport->interact_bank,
            ]);

            return $this->success(new BookingReceiptResource($passport), 'File Updated');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $booking_id, string $id)
    {
        try {
            $passport = BookingReceipt::find($id);

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
