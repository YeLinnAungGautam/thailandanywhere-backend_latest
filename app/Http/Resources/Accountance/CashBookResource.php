<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\ChartOfAccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'chart_of_accounts' => $this->chartOfAccounts,
            'interact_bank' => $this->interact_bank,
            'description' => $this->description,
            'amount' => $this->amount,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'cash_book_images' => CashBookImageResource::collection($this->cashBookImages),

            'cash_images' => CashImageResource::collection($this->bCashImages),
        ];
    }
}
