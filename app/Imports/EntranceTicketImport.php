<?php

namespace App\Imports;

use App\Models\EntranceTicket;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EntranceTicketImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if(is_null($row['product_id']) || trim($row['product_id']) == '') {
                EntranceTicket::create($this->getData($row));
            } else {
                $entrance_ticket = EntranceTicket::find($row['product_id']);

                if(is_null($entrance_ticket)) {
                    throw new Exception('Invalid product ID. Please check your product ID or connect with admins');
                }

                $entrance_ticket->update($this->getData($row));
            }
        }
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }

    private function getData($row)
    {
        return [
            'name' => $row['name'],
            'description' => $row['description'],
            'provider' => $row['provider'],
            'place' => $row['place'],
            'legal_name' => $row['legal_name'],
            'bank_name' => $row['bank_name'],
            'payment_method' => $row['payment_method'],
            'bank_account_number' => $row['bank_account_no'],
            'account_name' => $row['bank_account_name'],
            'cancellation_policy_id' => $row['cancellation_policy_id'],
        ];
    }
}
