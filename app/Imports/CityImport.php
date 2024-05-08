<?php

namespace App\Imports;

use App\Models\City;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CityImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    /**
     * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            City::firstOrCreate(['name' => $row['name']]);
        }
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
