<?php

namespace App\Exports;

use App\Models\EntranceTicket;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EntranceTicketExport implements FromCollection, WithHeadings, WithMapping
{
    protected $index = 0;

    public function headings(): array
    {
        return [
            '#',
            'Name',
            'Description',
            'Cover Image',
            'Place',
            'Legal Name',
            'Bank Name',
            'Payment Method',
            'Bank Account No',
            'Bank Account Name',
            'Images'
        ];
    }

    public function collection()
    {
        return EntranceTicket::query()->with('images')->get();
    }

    public function map($ticket): array
    {
        $images = $ticket->images->map(fn ($image) => get_file_link('images', $image->image))->implode(', ');

        return [
            ++$this->index,
            $ticket->name,
            $ticket->description,
            get_file_link('images', $ticket->cover_image),
            $ticket->place,
            $ticket->legal_name,
            $ticket->payment_method,
            $ticket->bank_name,
            $ticket->bank_account_number,
            $ticket->account_name,
            $images
        ];
    }
}
