<?php

namespace App\Imports;

use App\Models\PrivateVanTour;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PrivateVanTourImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                $this->validate($row, $this->createRules());

                PrivateVanTour::create($this->getData($row));
            } else {
                $private_van_tour = PrivateVanTour::find($row['product_id']);

                if(is_null($private_van_tour)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $this->validate($row, $this->updateRules($private_van_tour));

                $private_van_tour->update($this->getData($row));
            }
        }
    }

    private function getData($row)
    {
        return [
            'name' => $row['name'],
            'description' => $row['description'],
            'type' => $row['type'] ?? PrivateVanTour::TYPES['van_tour'],
            'sku_code' => str_replace('"', '', $row['sku_code']),
            'long_description' => $row['long_description'],
        ];
    }

    private function validate($row, $rules)
    {
        $validator = Validator::make($row->toArray(), $rules);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }
    }

    private function createRules(): array
    {
        return [
            'name' => 'required',
            'type' => 'nullable|in:van_tour,car_rental',
            'sku_code' => 'required|' . Rule::unique('private_van_tours'),
        ];
    }

    private function updateRules($private_van_tour): array
    {
        return [
            'name' => 'required',
            'type' => 'nullable|in:van_tour,car_rental',
            'sku_code' => 'required|unique:private_van_tours,sku_code,' . $private_van_tour->id,
        ];
    }
}
