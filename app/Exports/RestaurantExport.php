<?php

namespace App\Exports;

use App\Models\Restaurant;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RestaurantExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'Name',
            'Description',
            'City',
            'Place',
            'Contract Due',
            'Payment Method',
            'Bank Name',
            'Bank Account No',
            'Bank Account Name',
        ];
    }

    public function collection()
    {
        return Restaurant::query()->with('city')->get();
    }

    public function map($restaurant): array
    {
        return [
            $restaurant->id,
            $restaurant->name,
            $restaurant->description,
            $restaurant->city->name,
            $restaurant->place,
            $restaurant->contract_due,
            $restaurant->payment_method,
            $restaurant->bank_name,
            $restaurant->bank_account_number,
            $restaurant->account_name,
        ];
    }
}
