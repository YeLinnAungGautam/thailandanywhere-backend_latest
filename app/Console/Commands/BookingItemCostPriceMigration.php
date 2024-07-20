<?php

namespace App\Console\Commands;

use App\Models\BookingItem;
use App\Services\BookingItemDataService;
use Illuminate\Console\Command;

class BookingItemCostPriceMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:cost-price';

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
        $booking_items = BookingItem::whereNull('cost_price')->get();

        foreach($booking_items as $booking_item) {
            $service = new BookingItemDataService($booking_item);

            $total_cost_price = $service->getTotalCost();
            $cost_price = $service->getCostPrice();

            $booking_item->update([
                'cost_price' => $cost_price,
                'total_cost_price' => $total_cost_price,
            ]);
        }
    }
}
