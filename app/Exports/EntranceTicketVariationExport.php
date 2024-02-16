<?php

namespace App\Exports;

use App\Models\EntranceTicketVariation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EntranceTicketVariationExport implements FromCollection, WithHeadings, WithMapping
{
    protected $index = 0;

    public function headings(): array
    {
        return [
            '#',
            'Entrance Ticket',
            'Name',
            'Description',
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
            ++$this->index,
            $ticket_variation->entranceTicket->name ?? '-',
            $ticket_variation->name,
            $ticket_variation->description,
            $ticket_variation->cost_price,
            $ticket_variation->agent_price,
            $ticket_variation->price,
        ];
    }
}
