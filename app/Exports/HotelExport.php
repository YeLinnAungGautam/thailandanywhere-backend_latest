<?php

namespace App\Exports;

use App\Models\Hotel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HotelExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'Name',
            'Description',
            'Full Description',
            'Full Description (EN)',
            'Type',
            'City',
            'Place',
            'Legal Name',
            'Contract Due',
            'Payment Method',
            'Bank Name',
            'Bank Account Number',
            'Account Name',
            'Location Map Title',
            'Location Map',
            'Rating',
        ];
    }

    public function collection()
    {
        return Hotel::query()->with('city')->get();
    }

    public function map($hotel): array
    {
        return [
            $hotel->id,
            $hotel->name,
            $hotel->description,
            $hotel->full_description,
            $hotel->full_description_en,
            Hotel::TYPES[$hotel->type],
            $hotel->city->name ?? '-',
            $hotel->place,
            $hotel->legal_name,
            $hotel->contract_due,
            $hotel->payment_method,
            $hotel->bank_name,
            $hotel->bank_account_number,
            $hotel->account_name,
            $hotel->location_map_title,
            $hotel->location_map,
            $hotel->rating
        ];
    }
}
