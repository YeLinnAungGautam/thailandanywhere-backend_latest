<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CashImage;
use Illuminate\Http\Request;

class InternalTransferController extends Controller
{
    public function store(Request $request)
    {
        $receipt = $request->all();
        $relatable_type = $request->relatable_type;
        $relatable_id = $request->relatable_id;
        $is_internal_transfer = filter_var($receipt['is_internal_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Handle internal transfer
        if ($is_internal_transfer) {
            $exchange_rate = $receipt['exchange_rate'] ?? 1;
            $note = $receipt['note'] ?? null;
            $id = $receipt['id'] ?? null;

            // Create internal transfer record
            if($id) {
                $internalTransfer = \App\Models\InternalTransfer::find($id);
                $internalTransfer->update([
                    'exchange_rate' => $exchange_rate,
                    'note' => $note,
                ]);
            }else{
                $internalTransfer = \App\Models\InternalTransfer::create([
                    'exchange_rate' => $exchange_rate,
                    'notes' => $note,
                ]);
            }


            // Handle "from" files (source)
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

            // Handle "to" files (destination)
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
    }
}
