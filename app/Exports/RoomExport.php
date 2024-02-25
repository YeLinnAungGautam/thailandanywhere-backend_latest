<?php

namespace App\Exports;

use App\Models\Room;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RoomExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'Hotel',
            'Name',
            'Description',
            'Is Extra',
            'Extra Price',
            'Room Price',
            'Cost Price',
            'Agent Price',
            'Max Person',
        ];
    }

    public function collection()
    {
        return Room::query()->with('hotel')->get();
    }

    public function map($room): array
    {
        return [
            $room->id,
            $room->hotel->name,
            $room->name,
            $room->description,
            $room->is_extra ?? '-',
            $room->extra_price,
            $room->room_price,
            $room->cost,
            $room->agent_price,
            $room->max_person,
        ];
    }
}
