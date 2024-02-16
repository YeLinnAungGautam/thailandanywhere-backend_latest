<?php

namespace App\Exports;

use App\Models\Airline;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AirlineExport implements FromCollection, WithHeadings, WithMapping
{
    protected $index = 0;

    public function headings(): array
    {
        return [
            '#',
            'Name',
            'Legal Name',
            'Starting Balance',
        ];
    }

    public function collection()
    {
        return Airline::query()->get();
    }

    public function map($airline): array
    {
        return [
            ++$this->index,
            $airline->name,
            $airline->legal_name,
            $airline->starting_balance,
        ];
    }
}
