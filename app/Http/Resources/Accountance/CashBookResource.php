<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\Accountance\Detail\CashBookChartOfAccountResource;
use App\Http\Resources\ChartOfAccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CashBookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference_number,
            'date' => $this->date ? $this->date->format('d-m-Y H:i:s') : null,
            'income_or_expense' => $this->income_or_expense,
            'cash_structure' => new CashStructureResource($this->cashStructure),
            'cash_images' => CashImageResource::collection($this->cashImages),
            // 'chart_of_accounts' => ChartOfAccountResource::collection($this->chartOfAccounts),
            'chart_of_accounts' => CashBookChartOfAccountResource::collection($this->chartOfAccounts),
            // 'chart_of_accounts' => $this->chartOfAccounts,
            'interact_bank' => $this->interact_bank,
            'description' => $this->description,
            'amount' => $this->amount,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'cash_book_images' => CashBookImageResource::collection($this->cashBookImages),

            'cash_images' => $this->formatReceiptImages(),
        ];
    }

    private function formatReceiptImages()
    {
        if (!isset($this->cashImages)) {
            return '';
        }

        // Load internal transfer relationships
        $cashImages = $this->cashImages->load('internalTransfers');

        // Group cash images by internal transfer
        $internalTransferGroups = [];
        $regularCashImages = [];

        foreach ($cashImages as $cashImage) {
            // Check if this is an internal transfer
            if ($cashImage->internal_transfer) {
                // Get the internal transfer
                $internalTransfer = $cashImage->internalTransfers->first();

                if ($internalTransfer) {
                    $transferId = $internalTransfer->id;

                    // Initialize the group if not exists
                    if (!isset($internalTransferGroups[$transferId])) {
                        $internalTransferGroups[$transferId] = [
                            'is_internal_transfer' => true,
                            'internal_transfer_id' => $transferId,
                            'exchange_rate' => $internalTransfer->exchange_rate,
                            'notes' => $internalTransfer->notes,
                            'from_files' => [],
                            'to_files' => [],
                        ];
                    }

                    // Format the cash image data
                    $imageData = [
                        'id' => $cashImage->id,
                        'image' => $cashImage->image ? Storage::url('images/' . $cashImage->image) : null,
                        'date' => $cashImage->date ? $cashImage->date->format('Y-m-d H:i') : null,
                        'sender' => $cashImage->sender,
                        'receiver' => $cashImage->receiver,
                        'amount' => (float) $cashImage->amount,
                        'currency' => $cashImage->currency,
                        'interact_bank' => $cashImage->interact_bank,
                        'created_at' => $cashImage->created_at->format('d-m-Y H:i:s'),
                        'updated_at' => $cashImage->updated_at->format('d-m-Y H:i:s'),
                    ];

                    // Get direction from pivot
                    $direction = $internalTransfer->pivot->direction ?? null;

                    if ($direction === 'from') {
                        $internalTransferGroups[$transferId]['from_files'][] = $imageData;
                    } elseif ($direction === 'to') {
                        $internalTransferGroups[$transferId]['to_files'][] = $imageData;
                    }
                }
            } else {
                // Regular cash image - add to collection for CashImageResource
                $regularCashImages[] = $cashImage;
            }
        }

        // Convert regular cash images using CashImageResource
        $formattedRegularImages = CashImageResource::collection(collect($regularCashImages))->resolve();

        // Combine regular images (from CashImageResource) and internal transfer groups
        return array_merge($formattedRegularImages, array_values($internalTransferGroups));
    }
}
