<?php

namespace App\Imports;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductCategoryImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    /**
     * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            ProductCategory::firstOrCreate(['name' => $row['name']]);
        }
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
