<?php

namespace App\Exports;

use App\Models\GroupTour;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GroupTourExport implements FromCollection, WithHeadings, WithMapping
{
    protected $index = 0;

    public function headings(): array
    {
        return [
            '#',
            'SKU Code',
            'Name',
            'Description',
            'Cover Image',
            'Price',
            'Images'
        ];
    }

    public function collection()
    {
        return GroupTour::query()->with('images')->get();
    }

    public function map($group_tour): array
    {
        $images = $group_tour->images->map(fn ($image) => get_file_link('images', $image->image))->implode(', ');

        return [
            ++$this->index,
            $group_tour->sku_code,
            $group_tour->name,
            $group_tour->description,
            get_file_link('images', $group_tour->cover_image),
            $group_tour->price,
            $images
        ];
    }
}
