<?php

namespace App\Exports;

use App\Models\Meal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MealExport implements FromCollection, WithHeadings, WithMapping
{
    protected $index = 0;

    public function headings(): array
    {
        return [
            '#',
            'Restaurant',
            'Name',
            'Description',
            'Extra Price',
            'Meal Price',
            'Cost',
            'Max Person',
            'Is Extra',
            'Images'
        ];
    }

    public function collection()
    {
        return Meal::query()->with('restaurant', 'images')->get();
    }

    public function map($meal): array
    {
        $images = $meal->images->map(fn ($image) => get_file_link('images', $image->image))->implode(', ');

        return [
            ++$this->index,
            $meal->restaurant->name,
            $meal->name,
            $meal->description,
            $meal->extra_price,
            $meal->meal_price,
            $meal->cost,
            $meal->max_person,
            $meal->is_extra,
            $images
        ];
    }
}
