<?php

namespace App\Exports;

use App\Models\GroupTour;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GroupTourExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'SKU Code',
            'Name',
            'Description',
            'Cover Image',
            'Price',
            'Cancellation Policy ID',
        ];
    }

    public function collection()
    {
        return GroupTour::query()->with('images')->get();
    }

    public function map($group_tour): array
    {
        return [
            $group_tour->id,
            $group_tour->sku_code,
            $group_tour->name,
            $group_tour->description,
            get_file_link('images', $group_tour->cover_image),
            $group_tour->price,
            $group_tour->cancellation_policy_id,
        ];
    }
}
