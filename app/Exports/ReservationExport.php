<?php

namespace App\Exports;

use App\Models\Airline;
use App\Models\BookingItem;
use App\Models\EntranceTicket;
use App\Models\GroupTour;
use App\Models\Hotel;
use App\Models\PrivateVanTour;
use App\Services\BookingItemDataService;
use Exception;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReservationExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    protected $index = 0;

    public function __construct(
        public string $daterange,
        public string $product,
        public string $filter_type = 'service_date'
    ) {

    }

    public function headings(): array
    {
        return [
            [$this->daterange],
            [
                '#',
                'CRM ID',
                'Customer Name',

                'Product Type',
                'Product Name',
                'Variation Name',

                'Sale Payment Status',
                'Sale Amount',

                'Reservation Payment Status',
                'Expense Unit Cost',

                'Quantity',
                'Total Expense',

                'Service Date',
                'Sales Date',
            ]
        ];
    }

    public function map($booking_item): array
    {
        return [
            ++$this->index,
            $booking_item->crm_id,
            $booking_item->booking->customer->name,

            $booking_item->acsr_product_type_name,
            $booking_item->product->name ?? '-',
            $booking_item->acsr_variation_name,

            make_title($booking_item->booking->payment_status),
            $booking_item->selling_price,

            make_title($booking_item->payment_status),
            $booking_item->cost_price,

            (new BookingItemDataService($booking_item))->getQuantity(),
            (new BookingItemDataService($booking_item))->getTotalCost(),

            $booking_item->service_date,
            $booking_item->booking->booking_date
        ];
    }

    public function collection()
    {
        return $this->getData();
    }

    private function getData()
    {
        $dates = explode(',', $this->daterange);

        $query = $this->getQueryByProductType()
            ->with(
                'booking:id,customer_id,booking_date,payment_method,payment_status',
                'booking.customer:id,name',
                'product'
            );

        if ($this->filter_type == 'sale_date') {
            $query = $query->whereIn('booking_id', function ($q) use ($dates) {
                $q->select('id')
                    ->from('bookings')
                    ->where('booking_date', '>=', $dates[0])
                    ->where('booking_date', '<=', $dates[1]);
            });
        } else {
            $query = $query->whereBetween('service_date', [$dates[0], $dates[1]]);
        }

        return $query->get();
    }

    private function getQueryByProductType()
    {
        switch ($this->product) {
            case 'hotel':
                return BookingItem::query()->where('product_type', Hotel::class);

                break;

            case 'entrance_ticket':
                return BookingItem::query()->where('product_type', EntranceTicket::class);

                break;

            case 'private_van_tour':
                return BookingItem::query()->where('product_type', PrivateVanTour::class);

                break;

            case 'airline':
                return BookingItem::query()->where('product_type', Airline::class);

                break;

            case 'group_tour':
                return BookingItem::query()->where('product_type', GroupTour::class);

                break;

            default:
                throw new Exception('Invalid product type');

                break;
        }
    }
}
