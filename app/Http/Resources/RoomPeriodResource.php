<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomPeriodResource extends JsonResource
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
            'room_id' => $this->room_id,
            'period_name' => $this->period_name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'sale_price' => $this->sale_price,
            'cost_price' => $this->cost_price,
            'agent_price' => $this->agent_price,
            'score' => $this->calculateScore(), // Add score calculation
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Calculate score based on sale_price and cost_price
     * Formula: (sale_price - cost_price) / sale_price
     */
    private function calculateScore()
    {
        if ($this->sale_price > 0) {
            return round(($this->sale_price - $this->cost_price) / $this->sale_price, 3);
        }
        return 0;
    }
}
