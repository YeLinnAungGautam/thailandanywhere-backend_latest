<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxReceiptRequest;
use App\Http\Resources\TaxReceiptResource;
use App\Models\BookingItemGroup;
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
        $query = TaxReceipt::with(['product', 'groups']);

        if ($request->product_type) {
            $query->where('product_type', $request->product_type);
        }

        if($request->invoice_number){
            $query->where('invoice_number', $request->invoice_number);
        }

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if($request->group_id){
            $query->whereHas('groups', function($query) use ($request){
                $query->where('booking_item_groups.id', $request->group_id);
            });
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

            if ($request->has('group_ids')) {
                $groupIds = $request->group_ids ?? [];

                if(!empty($groupIds)) {
                    $validGroupIds = BookingItemGroup::whereIn('id', $groupIds)->pluck('id')->toArray();
                    if(count($validGroupIds) !== count($groupIds)) {
                        throw new \Exception('Invalid group ids provided');
                    }
                }
                $taxReceipt->groups()->sync($request->group_ids);
            }

            DB::commit();

            $taxReceipt->load(['product', 'groups']);

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
        $taxReceipt->load(['product', 'groups']);
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

            if ($request->has('group_ids')) {
                $groupIds = $request->group_ids ?? [];

                // Validate group_ids
                if (!empty($groupIds)) {
                    $validGroups = BookingItemGroup::whereIn('id', $groupIds)->pluck('id')->toArray();
                    if (count($validGroups) !== count($groupIds)) {
                        throw new \Exception('Some booking item groups do not exist');
                    }
                }

                $taxReceipt->groups()->sync($groupIds);
            }

            DB::commit();

            $taxReceipt->load(['product', 'groups']);

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
    public function syncReservations(Request $request, TaxReceipt $taxReceipt)
    {
        $request->validate([
            'group_ids' => 'array', // Allow empty array to detach all
            'group_ids.*' => 'exists:booking_item_groups,id' // FIXED: was booking_items
        ]);

        try {
            DB::beginTransaction();

            $oldGroups = $taxReceipt->groups()->pluck('booking_item_group_id')->toArray();
            $newGroups = $request->group_ids ?? [];

            $taxReceipt->groups()->sync($newGroups);

            DB::commit();

            $taxReceipt->load('groups');

            $attached = array_diff($newGroups, $oldGroups);
            $detached = array_diff($oldGroups, $newGroups);

            $message = 'Reservations synced successfully';
            if (count($attached) > 0 || count($detached) > 0) {
                $message .= sprintf(' (%d attached, %d detached)', count($attached), count($detached));
            }

            return $this->success(new TaxReceiptResource($taxReceipt), $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to sync reservations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available booking item groups for attaching to tax receipt
     */

    /**
     * Get currently attached groups for a tax receipt
     */
}
