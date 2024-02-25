<?php

namespace App\Imports;

use App\Models\EntranceTicket;
use App\Models\EntranceTicketVariation;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EntranceTicketVariationImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                EntranceTicketVariation::create($this->getData($row));
            } else {
                $entrance_ticket_variation = EntranceTicketVariation::find($row['product_id']);

                if(is_null($entrance_ticket_variation)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $entrance_ticket_variation->update($this->getData($row));
            }
        }
    }

    public function rules(): array
    {
        return [
            'entrance_ticket' => 'required',
            'name' => 'required'
        ];
    }

    private function getData($row)
    {
        $entrance_ticket = EntranceTicket::where('name', 'like', '%' . $row['entrance_ticket'] . '%')->first();

        if(is_null($entrance_ticket)) {
            throw new Exception('Invalid entrance ticket name. Please make sure the entrance name is correct');
        }

        return [
            'entrance_ticket_id' => $entrance_ticket->id,
            'name' => $row['name'],
            'price_name' => $row['price_name'],
            'price' => $row['price'],
            'cost_price' => $row['cost_price'],
            'agent_price' => $row['agent_price'],
            'description' => $row['description'],
        ];
    }
}
