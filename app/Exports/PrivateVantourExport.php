<?php

namespace App\Exports;

use App\Models\PrivateVanTour;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PrivateVantourExport implements FromCollection, WithHeadings, WithMapping
{
    protected $index = 0;

    public function headings(): array
    {
        return [
            '#',
            'SKU Code',
            'Name',
            'Description',
            'Type',
            'Long Description',
            'Cover Image',
            'Images'
        ];
    }

    public function collection()
    {
        return PrivateVanTour::query()->with('images')->get();
    }

    public function map($private_vantour): array
    {
        $images = $private_vantour->images->map(fn ($image) => get_file_link('images', $image->image))->implode(', ');

        return [
            ++$this->index,
            $private_vantour->sku_code,
            $private_vantour->name,
            $private_vantour->description,
            PrivateVanTour::TYPES[$private_vantour->type],
            $private_vantour->long_description,
            get_file_link('images', $private_vantour->cover_image),
            $images
        ];
    }
}
