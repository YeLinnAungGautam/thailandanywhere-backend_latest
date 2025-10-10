<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CashImage;
use App\Models\InternalTransfer;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalTransferController extends Controller
{
    use HttpResponses;

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $receipt = $request->all();
            $relatable_type = $request->relatable_type;
            $relatable_id = $request->relatable_id;
            $is_internal_transfer = filter_var($receipt['is_internal_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (!$is_internal_transfer) {
                DB::rollBack();
                return $this->error(null, 'Not an internal transfer', 400);
            }

            $exchange_rate = $receipt['exchange_rate'] ?? 1;
            $note = $receipt['note'] ?? null;

            // Create internal transfer record
            $internalTransfer = InternalTransfer::create([
                'exchange_rate' => $exchange_rate,
                'notes' => $note,
            ]);

            // Handle "from" files (source)
            $this->handleFromFiles($receipt, $internalTransfer, $relatable_id, $relatable_type);

            // Handle "to" files (destination)
            $this->handleToFiles($receipt, $internalTransfer, $relatable_id, $relatable_type);

            DB::commit();
            return $this->success($internalTransfer, 'Internal transfer created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $internalTransfer = InternalTransfer::find($id);

            if (!$internalTransfer) {
                DB::rollBack();
                return $this->error(null, 'Internal transfer not found', 404);
            }

            $receipt = $request->all();
            $relatable_type = $request->relatable_type;
            $relatable_id = $request->relatable_id;
            $exchange_rate = $receipt['exchange_rate'] ?? 1;
            $note = $receipt['note'] ?? null;

            // Update internal transfer record
            $internalTransfer->update([
                'exchange_rate' => $exchange_rate,
                'notes' => $note,
            ]);

            // Detach existing cash images and delete them
            $this->detachAndDeleteCashImages($internalTransfer);

            // Handle "from" files (source)
            $this->handleFromFiles($receipt, $internalTransfer, $relatable_id, $relatable_type);

            // Handle "to" files (destination)
            $this->handleToFiles($receipt, $internalTransfer, $relatable_id, $relatable_type);

            DB::commit();
            return $this->success($internalTransfer->fresh(), 'Internal transfer updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $internalTransfer = InternalTransfer::find($id);

            if (!$internalTransfer) {
                return $this->error(null, 'Internal transfer not found', 404);
            }

            // Detach and delete associated cash images
            $this->detachAndDeleteCashImages($internalTransfer);

            $internalTransfer->delete();

            DB::commit();
            return $this->success(null, 'Internal transfer deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Handle "from" files (source)
     */
    private function handleFromFiles($receipt, $internalTransfer, $relatable_id, $relatable_type)
    {
        if (isset($receipt['from_files']) && is_array($receipt['from_files'])) {
            foreach ($receipt['from_files'] as $fromFile) {
                if (!isset($fromFile['file'])) {
                    continue;
                }

                $fileDataFrom = $this->uploads($fromFile['file'], 'images/');

                $cashImageFrom = CashImage::create([
                    'relatable_id' => $relatable_id,
                    'relatable_type' => $relatable_type,
                    'date' => $fromFile['date'] ?? now(),
                    'sender' => $fromFile['sender'] ?? null,
                    'receiver' => $fromFile['receiver'] ?? null,
                    'amount' => $fromFile['amount'] ?? 0,
                    'currency' => $fromFile['currency'] ?? 'THB',
                    'interact_bank' => $fromFile['interact_bank'] ?? 'personal',
                    'image' => $fileDataFrom['fileName'],
                    'internal_transfer' => true,
                ]);

                $internalTransfer->cashImagesFrom()->attach($cashImageFrom->id, [
                    'direction' => 'from'
                ]);
            }
        }
    }

    /**
     * Handle "to" files (destination)
     */
    private function handleToFiles($receipt, $internalTransfer, $relatable_id, $relatable_type)
    {
        if (isset($receipt['to_files']) && is_array($receipt['to_files'])) {
            foreach ($receipt['to_files'] as $toFile) {
                if (!isset($toFile['file'])) {
                    continue;
                }

                $fileDataTo = $this->uploads($toFile['file'], 'images/');

                $cashImageTo = CashImage::create([
                    'relatable_id' => $relatable_id,
                    'relatable_type' => $relatable_type,
                    'date' => $toFile['date'] ?? now(),
                    'sender' => $toFile['sender'] ?? null,
                    'receiver' => $toFile['receiver'] ?? null,
                    'amount' => $toFile['amount'] ?? 0,
                    'currency' => $toFile['currency'] ?? 'THB',
                    'interact_bank' => $toFile['interact_bank'] ?? 'personal',
                    'image' => $fileDataTo['fileName'],
                    'internal_transfer' => true,
                ]);

                $internalTransfer->cashImagesTo()->attach($cashImageTo->id, [
                    'direction' => 'to'
                ]);
            }
        }
    }

    /**
     * Detach and delete cash images associated with internal transfer
     */
    private function detachAndDeleteCashImages($internalTransfer)
    {
        // Get all related cash images
        $fromImages = $internalTransfer->cashImagesFrom()->get();
        $toImages = $internalTransfer->cashImagesTo()->get();

        // Detach relationships
        $internalTransfer->cashImagesFrom()->detach();
        $internalTransfer->cashImagesTo()->detach();

        // Delete cash images
        foreach ($fromImages as $image) {
            $image->delete();
        }

        foreach ($toImages as $image) {
            $image->delete();
        }
    }
}
