<?php

namespace App\Exports;

use App\Models\BookingItem;
use App\Services\BookingItemDataService;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Excel;

class ReservationReportExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    protected $index = 0;
    protected $sale_daterange;

    public function __construct(string $sale_daterange)
    {
        $this->sale_daterange = $sale_daterange;
    }

    /**
    * It's required to define the fileName within
    * the export class when making use of Responsable.
    */
    private $fileName = "reservation_report.csv";

    /**
    * Optional Writer Type
    */
    private $writerType = Excel::CSV;

    /**
    * Optional headers
    */
    private $headers = [
        'Content-Type' => 'text/csv',
    ];

    public function headings(): array
    {
        return [
            [$this->sale_daterange],
            [
                '#',
                'CRM ID',
                'Customer Name',

                'Product Type',
                'Product Name',
                'Product Variation Name',
                'Sale Amount',
                'Expense Amount',

                'Payment Status',
                'Expense Status',
                'Sales Date',
                'Payment Method',
                'Link'
            ]
        ];
    }

    public function collection()
    {
        return $this->getData();
    }

    public function map($booking_item): array
    {
        return [
            ++$this->index,
            $booking_item->crm_id,
            $booking_item->booking->customer->name,

            $booking_item->acsr_product_type_name,
            $booking_item->product->name,
            $booking_item->acsr_variation_name,
            (new BookingItemDataService($booking_item))->getSalePrice(),
            (new BookingItemDataService($booking_item))->getTotalCost(),

            $booking_item->booking->payment_status,
            $booking_item->payment_status,
            $booking_item->booking->booking_date,
            $booking_item->booking->payment_method,
            'https://sales-admin.thanywhere.com/reservation/update/' . $booking_item->id . '/' . $booking_item->crm_id
        ];
    }

    private function getData()
    {
        $dates = explode(',', $this->sale_daterange);

        $records = BookingItem::query()
            ->with(
                'booking:id,customer_id,booking_date,payment_method,payment_status',
                'booking.customer:id,name',
                'product'
            )
            ->whereIn('payment_status', ['not_paid', 'partially_paid'])
            ->whereIn('booking_id', function ($q) use ($dates) {
                $q->select('id')
                    ->from('bookings')
                    ->where('booking_date', '>=', $dates[0])
                    ->where('booking_date', '<=', $dates[1]);
            })
            ->get();

        return $records;
    }
}
