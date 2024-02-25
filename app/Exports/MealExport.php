<?php

namespace App\Exports;

use App\Models\Meal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MealExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'Restaurant',
            'Name',
            'Description',
            'Extra Price',
            'Meal Price',
            'Cost',
            'Max Person',
            'Is Extra',
        ];
    }

    public function collection()
    {
        return Meal::query()->with('restaurant')->get();
    }

    public function map($meal): array
    {

        return [
            $meal->id,
            $meal->restaurant->name,
            $meal->name,
            $meal->description,
            $meal->extra_price,
            $meal->meal_price,
            $meal->cost,
            $meal->max_person,
            $meal->is_extra,
        ];
    }
}
