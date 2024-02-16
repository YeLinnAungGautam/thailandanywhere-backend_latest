<?php

namespace App\Exports;

use App\Models\Room;
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
            'Cost Price',
            'Agent Price',
            'Max Person',
            'Images'
        ];
    }

    public function collection()
    {
        return Room::query()->with('hotel', 'images')->get();
    }

    public function map($room): array
    {
        $images = $room->images->map(fn ($image) => get_file_link('images', $image->image))->implode(', ');

        return [
            ++$this->index,
            $room->hotel->name,
            $room->name,
            $room->description,
            $room->is_extra ?? '-',
            $room->extra_price,
            $room->room_price,
            $room->cost,
            $room->agent_price,
            $room->max_person,
            $images
        ];
    }
}
