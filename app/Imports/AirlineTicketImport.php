<?php

namespace App\Imports;

use App\Models\Airline;
use App\Models\AirlineTicket;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AirlineTicketImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                AirlineTicket::create($this->getData($row));
            } else {
                $airline_ticket = AirlineTicket::find($row['product_id']);

                if(is_null($airline_ticket)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $airline_ticket->update($this->getData($row));
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'airline' => 'required'
        ];
    }

    private function getData($row)
    {
        $airline = Airline::where('name', 'like', '%' . $row['airline'] . '%')->first();

        if(is_null($airline)) {
            throw new Exception('Invalid airline name. Please make sure the airline name is correct');
        }

        return [
            'airline_id' => $airline->id,
            'price' => $row['name'],
            'description' => $row['description'],
        ];
    }
}
