<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingItemGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateBookingItemGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:booking-item-group';

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
        Booking::with('items')->chunk(100, function ($bookings) {
            foreach ($bookings as $booking) {
                $grouped = $booking->items->groupBy('product_type');

                foreach ($grouped as $type => $items) {
                    $total_cost_price = $items->sum('cost_price');

                    $group = BookingItemGroup::create([
                        'booking_id' => $booking->id,
                        'product_type' => $type,
                        'total_cost_price' => $total_cost_price,
                    ]);

                    foreach ($items as $item) {
                        $item->update(['group_id' => $group->id]);
                    }
                }
            }
        });

        DB::table('reservation_customer_passports')->orderBy('id')->chunk(100, function ($passports) {
            foreach ($passports as $passport) {
                $bookingItem = BookingItem::find($passport->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                if (is_null($passport->name) && is_null($passport->passport_number) && is_null($passport->dob)) {
                    $meta = null;
                } else {
                    $meta = [
                        'name' => $passport->name,
                        'passport_number' => $passport->passport_number,
                        'dob' => $passport->dob,
                    ];
                }

                DB::table('customer_documents')->insert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'passport',
                    'file' => $passport->file,
                    'file_name' => $passport->file,
                    'meta' => $meta ? json_encode($meta) : null,
                ]);
            }
        });
    }
}
