<?php

namespace App\Imports;

use App\Models\GroupTour;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GroupTourImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                $this->validate($row, $this->createRules());

                GroupTour::create($this->getData($row));
            } else {
                $group_tour = GroupTour::find($row['product_id']);

                if(is_null($group_tour)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $this->validate($row, $this->updateRules($group_tour));

                $group_tour->update($this->getData($row));
            }
        }
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
            'price' => 'required',
            'sku_code' => 'required|' . Rule::unique('group_tours'),
        ];
    }

    private function updateRules($group_tour): array
    {
        return [
            'name' => 'required',
            'price' => 'required',
            'sku_code' => 'required|unique:group_tours,sku_code,' . $group_tour->id,
        ];
    }

    private function getData($row)
    {
        return [
            'sku_code' => $row['sku_code'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'cancellation_policy_id' => $row['cancellation_policy_id'],
        ];
    }
}
