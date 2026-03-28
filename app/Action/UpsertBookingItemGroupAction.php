<?php
namespace App\Action;

use App\Models\Booking;
use App\Models\BookingItemGroup;

class UpsertBookingItemGroupAction
{
    public static function execute(Booking $booking,array $itemsPassports = [])
    {
        $grouped = $booking->items->whereNull('group_id')->groupBy(function ($item) {
            return $item->product_type . ':' . $item->product_id;
        });

        foreach ($grouped as $key => $items) {
            [$product_type, $product_id] = explode(':', $key);

            $total_cost_price = $items->sum('cost_price');

            $group = BookingItemGroup::updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'product_type' => $product_type,
                    'product_id' => $product_id,
                ],
                [
                    'total_cost_price' => $total_cost_price,
                ]
            );

            foreach ($items as $item) {
                $item->update(['group_id' => $group->id]);

                $crm_suffix = (int) substr($item->crm_id, -3) - 1;
                $passports  = $itemsPassports[$crm_suffix] ?? [];

                foreach ($passports as $passport) {
                    if (empty($passport['name'])) continue;

                    $group->customerDocuments()->create([
                        'type'      => 'passport',
                        'file'      => null,
                        'file_name' => null,
                        'mime_type' => null,
                        'file_size' => null,
                        'meta'      => [
                            'name'            => $passport['name'],
                            'passport_number' => $passport['passport'] ?? null,
                        ],
                    ]);
                }
            }
        }
    }
}
