<?php

namespace App\Imports;

use App\Models\City;
use App\Models\Destination;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DestinationImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $city = City::where('name', 'like', '%' . $row['city_name'] . '%')->first();
            $product_category = ProductCategory::where('name', 'like', '%' . $row['product_category'] . '%')->first();

            Destination::firstOrCreate([
                'name' => $row['name'],
                'city_id' => $city->id,
                'category_id' => $product_category->id,
                'entry_fee' => $row['entrance_ticket_price']
            ]);
        }
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
