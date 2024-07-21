<?php

namespace App\Console\Commands;

use App\Models\BookingItem;
use App\Models\Hotel;
use Illuminate\Console\Command;

class MigrateHotelServiceDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:hotel-service-date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hotel_booking_items = BookingItem::where('product_type', Hotel::class)
            ->whereDate('created_at', '>=', '2024-01-01')
            ->whereDate('created_at', '<=', '2024-07-21')
            ->get();

        foreach($hotel_booking_items as $item) {
            $item->update(['service_date' => $item->checkin_date]);
        }

        $this->info('Hotel service dates are successfully migrated');
    }
}
