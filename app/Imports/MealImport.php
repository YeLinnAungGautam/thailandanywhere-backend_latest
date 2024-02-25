<?php

namespace App\Imports;

use App\Models\Meal;
use App\Models\Restaurant;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MealImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                Meal::create($this->getData($row));
            } else {
                $meal = Meal::find($row['product_id']);

                if(is_null($meal)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $meal->update($this->getData($row));
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'restaurant' => 'required'
        ];
    }

    private function getData($row)
    {
        $restaurant = Restaurant::where('name', 'like', '%' . $row['restaurant'] . '%')->first();

        if(is_null($restaurant)) {
            throw new Exception('Invalid restaurant name. Please make sure the restaurant name is correct');
        }

        return [
            'restaurant_id' => $restaurant->id,
            'name' => $row['name'],
            'cost' => $row['cost'],
            'extra_price' => $row['extra_price'],
            'meal_price' => $row['meal_price'],
            'description' => $row['description'],
            'max_person' => $row['max_person'],
            'is_extra' => $row['is_extra'] ?? 0
        ];
    }
}
