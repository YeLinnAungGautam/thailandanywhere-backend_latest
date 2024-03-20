<?php

namespace App\Console\Commands;

use App\Models\BookingItem;
use App\Services\BookingItemDataService;
use Illuminate\Console\Command;

class MigrateTotalCostPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:total-cost-price';

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
        BookingItem::chunk(100, function ($booking_items) {
            foreach($booking_items as $booking_item) {
                $booking_item->update(['total_cost_price' => (new BookingItemDataService($booking_item))->getTotalCost()]);
            }
        });
    }
}
