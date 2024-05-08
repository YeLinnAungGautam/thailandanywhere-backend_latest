<?php

namespace App\Exports;

use App\Models\PrivateVanTour;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PrivateVantourExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'SKU Code',
            'Name',
            'Description',
            'Type',
            'Long Description',
            'Cover Image',
            'Destination',
            'City',
            'Car'
        ];
    }

    public function collection()
    {
        return PrivateVanTour::query()->get();
    }

    public function map($private_vantour): array
    {
        $cars = [];

        foreach($private_vantour->cars as $car) {
            $cars[] = $car->name . '__' . $car->pivot->price;
        }

        return [
            $private_vantour->id,
            '"' . $private_vantour->sku_code . '"',
            $private_vantour->name,
            $private_vantour->description,
            PrivateVanTour::TYPES[$private_vantour->type],
            $private_vantour->long_description,
            get_file_link('images', $private_vantour->cover_image),
            $private_vantour->destinations->pluck('name')->join(', '),
            $private_vantour->cities->pluck('name')->join(', '),
            collect($cars)->join(', '),
        ];
    }
}
