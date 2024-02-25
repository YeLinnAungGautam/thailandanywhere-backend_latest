<?php

namespace App\Imports;

use App\Models\City;
use App\Models\Hotel;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class HotelsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                Hotel::create($this->getData($row));
            } else {
                $hotel = Hotel::find($row['product_id']);

                if(is_null($hotel)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $hotel->update($this->getData($row));
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'type' => 'nullable|in:direct_booking,other_booking',
            'contract_due' => 'nullable|date_format:Y-m-d H:i:s',
            'place' => 'required'
        ];
    }

    private function getData($row)
    {
        $city = City::where('name', 'like', '%' . $row['city'] . '%')->first();

        if(is_null($city)) {
            throw new Exception('Invalid city name. Please make sure the city name is correct');
        }

        return [
            'name' => $row['name'],
            'description' => $row['description'],
            'type' => $row['type'] ?? Hotel::TYPES['direct_booking'],
            'city_id' => $city->id,
            'place' => $row['place'],
            'payment_method' => $row['payment_method'],
            'bank_name' => $row['bank_name'],
            'bank_account_number' => $row['bank_account_number'],
            'account_name' => $row['bank_account_name'],
            'legal_name' => $row['legal_name'],
            'contract_due' => $row['contract_due'],
        ];
    }
}
