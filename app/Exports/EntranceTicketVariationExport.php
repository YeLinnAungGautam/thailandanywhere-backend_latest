<?php

namespace App\Exports;

use App\Models\EntranceTicketVariation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EntranceTicketVariationExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'Entrance Ticket',
            'Name',
            'Description',
            'Price Name',
            'Cost Price',
            'Agent Price',
            'Price'
        ];
    }

    public function collection()
    {
        return EntranceTicketVariation::query()->with('entranceTicket')->get();
    }

    public function map($ticket_variation): array
    {
        return [
            $ticket_variation->id,
            $ticket_variation->entranceTicket->name ?? '-',
            $ticket_variation->name,
            $ticket_variation->description,
            $ticket_variation->price_name,
            $ticket_variation->cost_price,
            $ticket_variation->agent_price,
            $ticket_variation->price,
        ];
    }
}
