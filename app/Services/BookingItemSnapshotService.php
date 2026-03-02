<?php

namespace App\Services;

use App\Models\BookingItem;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Car;
use App\Models\PrivateVanTour;
use App\Models\EntranceTicket;
use App\Models\EntranceTicketVariation;
use App\Models\AirlineTicket;
use App\Models\Airline;
use App\Models\GroupTour;
use App\Models\AirportPickup;
use Illuminate\Support\Facades\Log;

class BookingItemSnapshotService
{
    /**
     * BookingItem တစ်ခုအတွက် snapshot အားလုံး build လုပ်မည်
     */
    public function buildSnapshot(BookingItem $item): array
    {
        $productSnapshot   = $this->buildProductSnapshot($item);
        $variationSnapshot = $this->buildVariationSnapshot($item);
        $priceSnapshot     = $this->buildPriceSnapshot($item);

        return [
            'product_snapshot'   => $productSnapshot,
            'variation_snapshot' => $variationSnapshot,
            'price_snapshot'     => $priceSnapshot,
            'archive_snapshot'   => [
                'product'   => $productSnapshot,
                'variation' => $variationSnapshot,
                'price'     => $priceSnapshot,
                'meta' => [
                    'product_type'  => $item->product_type,
                    'product_id'    => $item->product_id,
                    'snapshotted_at' => now()->toDateTimeString(),
                ],
            ],
        ];
    }

    /**
     * Product Snapshot - Hotel, PrivateVanTour, EntranceTicket, etc.
     */
    private function buildProductSnapshot(BookingItem $item): ?array
    {
        try {
            $product = match($item->product_type) {
                Hotel::class          => Hotel::withTrashed()->find($item->product_id),
                PrivateVanTour::class => PrivateVanTour::withTrashed()->find($item->product_id),
                EntranceTicket::class => EntranceTicket::withTrashed()->find($item->product_id),
                Airline::class        => Airline::withTrashed()->find($item->product_id),
                GroupTour::class      => GroupTour::withTrashed()->find($item->product_id),
                AirportPickup::class  => AirportPickup::withTrashed()->find($item->product_id),
                default               => null,
            };

            if (!$product) return null;

            return $product->toArray();

        } catch (\Exception $e) {
            Log::error('Product snapshot error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Variation Snapshot - Room, Car, Ticket, EntranceTicketVariation
     */
    private function buildVariationSnapshot(BookingItem $item): ?array
    {
        try {
            return match($item->product_type) {
                Hotel::class          => $this->snapshotRoom($item->room_id),
                PrivateVanTour::class => $this->snapshotCar($item->car_id),
                EntranceTicket::class => $this->snapshotTicketVariation($item->variation_id),
                Airline::class        => $this->snapshotAirlineTicket($item->ticket_id),
                GroupTour::class      => $this->snapshotGroupTour($item->product_id),
                AirportPickup::class  => null, // AirportPickup မှာ variation မရှိ
                default               => null,
            };
        } catch (\Exception $e) {
            Log::error('Variation snapshot error: ' . $e->getMessage());
            return null;
        }
    }

    private function snapshotRoom(?int $roomId): ?array
    {
        if (!$roomId) return null;
        $room = Room::withTrashed()->with(['hotel'])->find($roomId);
        return $room?->toArray();
    }

    private function snapshotCar(?int $carId): ?array
    {
        if (!$carId) return null;
        $car = Car::withTrashed()->find($carId);
        return $car?->toArray();
    }

    private function snapshotTicketVariation(?int $variationId): ?array
    {
        if (!$variationId) return null;
        $variation = EntranceTicketVariation::withTrashed()->find($variationId);
        return $variation?->toArray();
    }

    private function snapshotAirlineTicket(?int $ticketId): ?array
    {
        if (!$ticketId) return null;
        $ticket = AirlineTicket::withTrashed()->find($ticketId);
        return $ticket?->toArray();
    }

    private function snapshotGroupTour(?int $productId): ?array
    {
        if (!$productId) return null;
        $groupTour = GroupTour::withTrashed()->find($productId);
        return $groupTour?->toArray();
    }

    /**
     * Price Snapshot - Booking item ၏ price အချက်အလက်
     */
    private function buildPriceSnapshot(BookingItem $item): array
    {
        return [
            'selling_price'      => $item->selling_price,
            'cost_price'         => $item->cost_price,
            'total_cost_price'   => $item->total_cost_price,
            'amount'             => $item->amount,
            'discount'           => $item->discount,
            'quantity'           => $item->quantity,
            'days'               => $item->days,
            'exchange_rate'      => $item->exchange_rate,
            'individual_pricing' => $item->individual_pricing,
            'payment_status'     => $item->payment_status,
            'payment_method'     => $item->payment_method,
            // Variation ၏ လက်ရှိ price (ယခု booking ဖြစ်ချိန်)
            'variation_current_price' => $this->getCurrentVariationPrice($item),
        ];
    }

    /**
     * Variation ၏ ယခုလက်ရှိ price ကို ထုတ်ယူ (bookmark အနေနဲ့)
     */
    private function getCurrentVariationPrice(BookingItem $item): ?array
    {
        try {
            return match($item->product_type) {
                Hotel::class => $this->getRoomCurrentPrice($item->room_id),
                PrivateVanTour::class => $this->getCarCurrentPrice($item->car_id),
                EntranceTicket::class => $this->getVariationCurrentPrice($item->variation_id),
                Airline::class => $this->getTicketCurrentPrice($item->ticket_id),
                default => null,
            };
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getRoomCurrentPrice(?int $roomId): ?array
    {
        if (!$roomId) return null;
        $room = Room::withTrashed()->find($roomId);
        if (!$room) return null;
        return [
            'price'          => $room->price ?? null,
            'adult_price'    => $room->adult_price ?? null,
            'child_price'    => $room->child_price ?? null,
            'extra_bed'      => $room->extra_bed ?? null,
        ];
    }

    private function getCarCurrentPrice(?int $carId): ?array
    {
        if (!$carId) return null;
        $car = Car::withTrashed()->find($carId);
        if (!$car) return null;
        return [
            'price' => $car->price ?? null,
        ];
    }

    private function getVariationCurrentPrice(?int $variationId): ?array
    {
        if (!$variationId) return null;
        $variation = EntranceTicketVariation::withTrashed()->find($variationId);
        if (!$variation) return null;
        return [
            'adult_price' => $variation->adult_price ?? null,
            'child_price' => $variation->child_price ?? null,
            'price'       => $variation->price ?? null,
        ];
    }

    private function getTicketCurrentPrice(?int $ticketId): ?array
    {
        if (!$ticketId) return null;
        $ticket = AirlineTicket::withTrashed()->find($ticketId);
        if (!$ticket) return null;
        return [
            'price' => $ticket->price ?? null,
        ];
    }

    /**
     * Booking item ၏ selling price နှင့် variation ၏ လက်ရှိ price တိုက်စစ်မည်
     * Return: ['is_price_changed' => bool, 'differences' => array]
     */
    public function comparePriceWithCurrent(BookingItem $item): array
    {
        $priceSnapshot = $item->price_snapshot;
        if (!$priceSnapshot) {
            return ['is_price_changed' => false, 'differences' => [], 'message' => 'No snapshot'];
        }

        $snapshotVariationPrice = $priceSnapshot['variation_current_price'] ?? null;
        $currentVariationPrice  = $this->getCurrentVariationPrice($item);

        if (!$snapshotVariationPrice || !$currentVariationPrice) {
            return ['is_price_changed' => false, 'differences' => [], 'message' => 'No variation price to compare'];
        }

        $differences = [];
        foreach ($snapshotVariationPrice as $key => $snapshotValue) {
            $currentValue = $currentVariationPrice[$key] ?? null;
            if ((float)$snapshotValue !== (float)$currentValue) {
                $differences[$key] = [
                    'snapshot_price' => $snapshotValue,
                    'current_price'  => $currentValue,
                ];
            }
        }

        return [
            'is_price_changed' => count($differences) > 0,
            'differences'      => $differences,
            'snapshot_price'   => $snapshotVariationPrice,
            'current_price'    => $currentVariationPrice,
        ];
    }
}


