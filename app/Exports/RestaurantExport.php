<?php

namespace App\Exports;

use App\Models\Restaurant;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RestaurantExport implements FromCollection, WithHeadings, WithMapping
{
    protected $index = 0;

    public function headings(): array
    {
        return [
            '#',
            'Name',
            'Description',
            'City',
            'Place',
            'Contract Due',
            'Payment Method',
            'Bank Name',
            'Bank Account No',
            'Bank Account Name',
            'Images'
        ];
    }

    public function collection()
    {
        return Restaurant::query()->with('city', 'images')->get();
    }

    public function map($restaurant): array
    {
        $images = $restaurant->images->map(fn ($image) => get_file_link('images', $image->image))->implode(', ');

        return [
            ++$this->index,
            $restaurant->name,
            $restaurant->description,
            $restaurant->city->name,
            $restaurant->place,
            $restaurant->contract_due,
            $restaurant->payment_method,
            $restaurant->bank_name,
            $restaurant->bank_account_number,
            $restaurant->account_name,
            $images
        ];
    }
}
