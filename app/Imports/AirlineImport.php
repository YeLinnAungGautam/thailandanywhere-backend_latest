<?php

namespace App\Imports;

use App\Models\Airline;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AirlineImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                Airline::create($this->getData($row));
            } else {
                $airline = Airline::find($row['product_id']);

                if(is_null($airline)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $airline->update($this->getData($row));
            }
        }
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }

    private function getData($row)
    {
        return [
            'name' => $row['name'],
            'legal_name' => $row['legal_name'],
            'starting_balance' => $row['starting_balance'],
        ];
    }
}
