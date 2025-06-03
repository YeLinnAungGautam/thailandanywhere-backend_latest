<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxReceiptRequest;
use App\Http\Resources\TaxReceiptResource;
use App\Models\TaxReceipt;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaxReceiptController extends Controller
{
    use HttpResponses;
    use ImageManager;

    // Index - List all tax receipts
    public function index(Request $request)
    {
        $query = TaxReceipt::with(['product', 'reservations']);

        if ($request->product_type) {
            $query->where('product_type', $request->product_type);
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('receipt_date', [$request->date_from, $request->date_to]);
        }

        $data = $query->latest()->paginate($request->limit ?? 10);

        return $this->success(TaxReceiptResource::collection($data)
        ->additional([
            'meta' => [
                'total_page' => (int) ceil($data->total() / $data->perPage()),
            ],
        ])
        ->response()
        ->getData(), 'Tax Receipt List');
    }

    // Store - Create new tax receipt
    public function store(TaxReceiptRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            $taxReceipt = new TaxReceipt();
            $taxReceipt->fill($data);

            // Handle receipt image upload if provided
            if($file = $request->file('receipt_image')) {
                $fileData = $this->uploads($file, 'images/');
                $taxReceipt->receipt_image = $fileData['fileName'];
            }

            $taxReceipt->save();

            DB::commit();

            $taxReceipt->load(['product', 'reservations']);

            return $this->success(new TaxReceiptResource($taxReceipt), 'Tax Receipt created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if exists
            if (isset($fileData)) {
                Storage::delete('images/' . $fileData['fileName']);
            }
            return $this->error('Failed to create Tax Receipt: ' . $e->getMessage(), 500);
        }
    }

    // Show - Get single tax receipt
    public function show(TaxReceipt $taxReceipt)
    {
        $taxReceipt->load(['product', 'reservations']);
        return $this->success(new TaxReceiptResource($taxReceipt), 'Tax Receipt retrieved successfully');
    }

    // Update - Update tax receipt
    public function update(TaxReceiptRequest $request, TaxReceipt $taxReceipt)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Handle receipt image upload if provided
            if($file = $request->file('receipt_image')) {
                // Delete old image if exists
                if ($taxReceipt->receipt_image) {
                    Storage::delete('images/' . $taxReceipt->receipt_image);
                }

                $fileData = $this->uploads($file, 'images/');
                $data['receipt_image'] = $fileData['fileName'];
            }

            $taxReceipt->update($data);

            DB::commit();

            $taxReceipt->load(['product', 'reservations']);

            return $this->success(new TaxReceiptResource($taxReceipt), 'Tax Receipt updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if exists
            if (isset($fileData)) {
                Storage::delete('images/' . $fileData['fileName']);
            }
            return $this->error('Failed to update Tax Receipt: ' . $e->getMessage(), 500);
        }
    }

    // Destroy - Delete tax receipt
    public function destroy(TaxReceipt $taxReceipt)
    {
        try {
            DB::beginTransaction();

            // Delete associated image if exists
            if ($taxReceipt->receipt_image) {
                Storage::delete('images/' . $taxReceipt->receipt_image);
            }

            $taxReceipt->delete();

            DB::commit();

            return $this->success(null, 'Tax Receipt deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete Tax Receipt: ' . $e->getMessage(), 500);
        }
    }

    // Attach reservations (booking items) to tax receipt
    public function attachReservations(Request $request, TaxReceipt $taxReceipt)
    {
        $request->validate([
            'reservation_ids' => 'required|array',
            'reservation_ids.*' => 'exists:booking_items,id'
        ]);

        try {
            DB::beginTransaction();

            $taxReceipt->reservations()->attach($request->reservation_ids);

            DB::commit();

            $taxReceipt->load('reservations');

            return $this->success(new TaxReceiptResource($taxReceipt), 'Reservations attached successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to attach reservations: ' . $e->getMessage(), 500);
        }
    }

    // Detach reservations (booking items) from tax receipt
    public function detachReservations(Request $request, TaxReceipt $taxReceipt)
    {
        $request->validate([
            'reservation_ids' => 'required|array',
            'reservation_ids.*' => 'exists:booking_items,id'
        ]);

        try {
            DB::beginTransaction();

            $taxReceipt->reservations()->detach($request->reservation_ids);

            DB::commit();

            $taxReceipt->load('reservations');

            return $this->success(new TaxReceiptResource($taxReceipt), 'Reservations detached successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to detach reservations: ' . $e->getMessage(), 500);
        }
    }

    // Sync reservations (replace all attached booking items)
    public function syncReservations(Request $request, TaxReceipt $taxReceipt)
    {
        $request->validate([
            'reservation_ids' => 'required|array',
            'reservation_ids.*' => 'exists:booking_items,id'
        ]);

        try {
            DB::beginTransaction();

            $taxReceipt->reservations()->sync($request->reservation_ids);

            DB::commit();

            $taxReceipt->load('reservations');

            return $this->success(new TaxReceiptResource($taxReceipt), 'Reservations synced successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to sync reservations: ' . $e->getMessage(), 500);
        }
    }
}
