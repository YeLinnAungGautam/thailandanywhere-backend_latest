<?php

namespace App\Exports;

use App\Models\EntranceTicket;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EntranceTicketExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return [
            'Product ID',
            'Name',
            'Provider',
            'Description',
            'Cover Image',
            'Place',
            'Legal Name',
            'Bank Name',
            'Payment Method',
            'Bank Account No',
            'Bank Account Name',
            'Cancellation Policy ID'
        ];
    }

    public function collection()
    {
        return EntranceTicket::query()->with('images')->get();
    }

    public function map($ticket): array
    {
        return [
            $ticket->id,
            $ticket->name,
            $ticket->provider,
            $ticket->description,
            get_file_link('images', $ticket->cover_image),
            $ticket->place,
            $ticket->legal_name,
            $ticket->payment_method,
            $ticket->bank_name,
            $ticket->bank_account_number,
            $ticket->account_name,
            $ticket->cancellation_policy_id
        ];
    }
}
