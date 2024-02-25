<?php

namespace App\Exports;

use App\Models\AirlineTicket;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AirlineTicketExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'Airline',
            'Name',
            'Description',
        ];
    }

    public function collection()
    {
        return AirlineTicket::query()->with('airline')->get();
    }

    public function map($airline_ticket): array
    {
        return [
            $airline_ticket->id,
            $airline_ticket->airline->name,
            $airline_ticket->price,
            $airline_ticket->description,
        ];
    }
}
