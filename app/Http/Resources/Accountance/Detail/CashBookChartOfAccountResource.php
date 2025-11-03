<?php

namespace App\Http\Resources\Accountance\Detail;

use App\Http\Resources\AccountClassResource;
use App\Http\Resources\AccountHeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashBookChartOfAccountResource extends JsonResource
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
            'account_code' => $this->account_code,
            'account_name' => $this->account_name,
            'account_class' => new AccountClassResource($this->accountClass),
            'account_head' => new AccountHeadResource($this->accountHead),
            'pivot' => $this->pivot,
        ];
    }
}
