<?php

namespace App\Imports;

use App\Models\Hotel;
use App\Models\Room;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RoomImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                Room::create($this->getData($row));
            } else {
                $room = Room::find($row['product_id']);

                if(is_null($room)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $room->update($this->getData($row));
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'hotel' => 'required'
        ];
    }

    private function getData($row)
    {
        $hotel = Hotel::where('name', 'like', '%' . $row['hotel'] . '%')->first();

        if(is_null($hotel)) {
            throw new Exception('Invalid hotel name. Please make sure the hotel name is correct');
        }

        return [
            'hotel_id' => $hotel->id,
            'name' => $row['name'],
            'cost' => $row['cost_price'],
            'extra_price' => $row['extra_price'],
            'room_price' => $row['room_price'],
            'description' => $row['description'],
            'max_person' => $row['max_person'],
            'is_extra' => $row['is_extra'] ?? 0,
            'agent_price' => $row['agent_price'],
        ];
    }
}
