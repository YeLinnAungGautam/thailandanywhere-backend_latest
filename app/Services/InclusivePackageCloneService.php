<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\InclusivePackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InclusivePackageCloneService
{
    // ══════════════════════════════════════════════════════════════
    // PUBLIC API
    // ══════════════════════════════════════════════════════════════

    /**
     * Clone an inclusive package and link it to the given booking.
     */
    public function cloneAndLink(int $originalPackageId, Booking $booking): InclusivePackage
    {
        return DB::transaction(function () use ($originalPackageId, $booking) {
            $original = InclusivePackage::findOrFail($originalPackageId);

            $clone = InclusivePackage::create([
                'package_name'        => $original->package_name . ' (Clone)',
                'adults'              => $booking->inclusive_quantity   ?? $original->adults,
                'children'            => $original->children,
                'start_date'          => $booking->inclusive_start_date ?? $original->start_date,
                'end_date'            => $booking->inclusive_end_date   ?? $original->end_date,
                'nights'              => $original->nights,
                'total_days'          => $original->total_days,
                'day_city_map'        => $original->day_city_map   ?? [],
                'attractions'         => $original->attractions    ?? [],
                'hotels'              => $original->hotels         ?? [],
                'van_tours'           => $original->van_tours      ?? [],
                'ordered_items'       => $original->ordered_items  ?? [],
                'descriptions'        => $original->descriptions   ?? [],
                'total_cost_price'    => $original->total_cost_price,
                'total_selling_price' => $original->total_selling_price,
                'rate_per_person'     => $original->rate_per_person,
                'status'              => 'active',
                'created_by'          => auth()->id(),
                'is_clone'            => true,
                'cloned_from_id'      => $original->id,
            ]);

            $booking->update(['inclusive_package_id' => $clone->id]);

            return $clone;
        });
    }

    /**
     * Sync a booking item change into the cloned package.
     *
     * @param string $action  'add' | 'update' | 'remove'
     *
     * NOTE: When action = 'add' or 'update', the service auto-detects whether the item
     * already exists in the package (by _booking_item_id) and promotes 'add' → 'update'
     * if a match is found. This prevents duplicate entries when the caller passes the
     * wrong action (e.g. always sending 'add' on every save).
     */
    public function syncItemToPackage(Booking $booking, array $changedItem, string $action): void
    {
        if (!$booking->inclusive_package_id) return;

        $package = InclusivePackage::find($booking->inclusive_package_id);
        if (!$package || !$package->is_clone) return;

        $field = $this->resolveField($changedItem['product_type'] ?? '');
        if (!$field) return;

        $items = $package->{$field} ?? [];

        // ── Auto-correct action ──────────────────────────────────────────────
        // If caller says 'add' but the item already exists → treat as 'update'.
        // If caller says 'update' but the item doesn't exist yet → treat as 'add'.
        // 'remove' is always trusted as-is.
        if ($action !== 'remove') {
            $exists = false;
            foreach ($items as $item) {
                if ($this->isSameItem($item, $changedItem)) {
                    $exists = true;
                    break;
                }
            }
            $action = $exists ? 'update' : 'add';
        }
        // ────────────────────────────────────────────────────────────────────

        if ($action === 'add') {
            $uid     = $this->generateNextUid($package);
            $items[] = $this->buildItemForAdd($changedItem, $field, $uid, $package);

        } elseif ($action === 'update') {
            foreach ($items as $i => $item) {
                if ($this->isSameItem($item, $changedItem)) {
                    $items[$i] = $this->mergeChangedFields($item, $changedItem, $field);
                    break;
                }
            }

        } elseif ($action === 'remove') {
            $items = array_values(
                array_filter($items, fn($item) => !$this->isSameItem($item, $changedItem))
            );
        }

        $package->update([$field => $items]);
        $this->syncOrderedItems($package, $changedItem, $action, $field);
    }

    // ══════════════════════════════════════════════════════════════
    // PRIVATE — ORDERED ITEMS SYNC
    // ══════════════════════════════════════════════════════════════

    private function syncOrderedItems(
        InclusivePackage $package,
        array $changedItem,
        string $action,
        string $field
    ): void {
        $typeMap      = ['van_tours' => 'van', 'attractions' => 'attraction', 'hotels' => 'hotel'];
        $type         = $typeMap[$field] ?? $field;
        $orderedItems = $package->ordered_items ?? [];

        if ($action === 'add') {
            $fieldItems = $package->{$field} ?? [];
            $lastItem   = end($fieldItems);
            if ($lastItem) {
                $orderedItems[] = array_merge($lastItem, ['_type' => $type]);
            }

        } elseif ($action === 'update') {
            $fieldItems = $package->{$field} ?? [];
            foreach ($orderedItems as $i => $oi) {
                if ($this->isSameItem($oi, $changedItem)) {
                    foreach ($fieldItems as $fi) {
                        if ($this->isSameItem($fi, $changedItem)) {
                            $orderedItems[$i] = array_merge($fi, ['_type' => $type]);
                            break;
                        }
                    }
                    break;
                }
            }

        } elseif ($action === 'remove') {
            $orderedItems = array_values(
                array_filter($orderedItems, fn($oi) => !$this->isSameItem($oi, $changedItem))
            );
        }

        $package->update(['ordered_items' => $orderedItems]);
    }

    // ══════════════════════════════════════════════════════════════
    // PRIVATE — BUILD ITEM FOR ADD
    // ══════════════════════════════════════════════════════════════

    /**
     * Build a fully-formatted new item that matches the structure of existing cloned items.
     *
     * IMPORTANT — sellingPrice and costPrice are always stored as numeric strings ("4300", "900")
     * so Vue's Number() cast works correctly when summing totals.
     *
     * dayLabel / checkInLabel / checkOutLabel are NOT stored — Model accessor calculates them.
     */
    private function buildItemForAdd(array $item, string $field, string $uid, InclusivePackage $package): array
    {
        $serviceDate = $item['serviceDate'] ?? $item['service_date'] ?? null;

        $dayNumber = isset($item['dayNumber'])
            ? $item['dayNumber']
            : $this->calculateDayNumber($serviceDate, $package);

        $base = [
            '_uid'             => $uid,
            '_booking_item_id' => $item['id']            ?? null,
            'product_id'       => $item['product_id']    ?? null,
            'product_name'     => $item['product_name']  ?? '',
            'product_type'     => $item['product_type']  ?? null,
            'product_image'    => $item['product_image'] ?? null,
            // ✅ Always numeric string — Vue Number("4300") = 4300, not string concat
            'sellingPrice'     => (string)(is_numeric($item['sellingPrice'] ?? null)
                                    ? $item['sellingPrice']
                                    : ($item['amount'] ?? 0)),
            'costPrice'        => (string)(is_numeric($item['costPrice'] ?? null)
                                    ? $item['costPrice']
                                    : ($item['total_cost_price'] ?? 0)),
            'serviceDate'      => $serviceDate,
            'service_date'     => $item['service_date']  ?? $serviceDate,
            'selling_price'    => (string)($item['selling_price'] ?? 0),
            'cost_price'       => $item['cost_price']    ?? 0,
            'quantity'         => $item['quantity']      ?? 1,
            'car_id'           => $item['car_id']        ?? null,
            'car_list'         => $item['car_list']      ?? [],
            'images'           => $item['images']        ?? [],
            'cities'           => $item['cities']        ?? [],
            'city'             => $item['city']          ?? '',
            'item_name'        => $item['item_name']     ?? ($item['product_name'] ?? ''),
            'dayNumber'        => $dayNumber,
        ];

        return match ($field) {
            'van_tours'   => $this->buildVanTourItem($base, $item),
            'attractions' => $this->buildAttractionItem($base, $item),
            'hotels'      => $this->buildHotelItem($base, $item, $package),
            default       => $base,
        };
    }

    private function buildVanTourItem(array $base, array $item): array
    {
        return array_merge($base, [
            'type'            => 'VanTour',
            'route'           => $item['product_name']    ?? ($item['route'] ?? ''),
            'passengers'      => (string)($item['quantity'] ?? 1),
            'cars'            => (string)($item['quantity'] ?? 1),
            'carId'           => (int)($item['car_id']    ?? 0),
            'carName'         => $item['item_name']       ?? '',
            'carCapacity'     => $item['carCapacity']     ?? null,
            'pickup_time'     => $item['pickup_time']     ?? null,
            'pickup_location' => $item['pickup_location'] ?? null,
            'route_plan'      => $item['route_plan']      ?? null,
            'vanTourId'       => $item['product_id']      ?? null,
            'vanTourName'     => $item['product_name']    ?? '',
            'vanTourType'     => $item['vanTourType']     ?? null,
            'agentPrice'      => $item['agentPrice']      ?? 0,
        ]);
    }

    private function buildAttractionItem(array $base, array $item): array
    {
        $ip       = $this->normalizeIndividualPricing($item['individual_pricing'] ?? []);
        $adultQty = (int)($ip['adult']['quantity'] ?? $item['quantity'] ?? 0);
        $childQty = (int)($ip['child']['quantity'] ?? 0);

        return array_merge($base, [
            'type'               => 'Attraction',
            'name'               => $item['product_name']  ?? '',
            'adults'             => (string)$adultQty,
            'children'           => $item['children']      ?? $childQty,
            'productId'          => $item['product_id']    ?? null,
            'productName'        => $item['product_name']  ?? '',
            'productType'        => $item['productType']   ?? null,
            'productImage'       => $item['product_image'] ?? null,
            'variationId'        => $item['car_id']        ?? null,
            'variation'          => $item['variation']     ?? null,
            'individual_pricing' => $ip,
            'child_info'         => $item['child_info']    ?? [],
            'adultPrice'         => $this->numericOrNull($ip['adult']['selling_price']    ?? null),
            'adultTotal'         => $this->numericOrNull($ip['adult']['amount']           ?? null),
            'adultCostPrice'     => $this->numericOrNull($ip['adult']['cost_price']       ?? null),
            'adultCostTotal'     => $this->numericOrNull($ip['adult']['total_cost_price'] ?? null),
            'childPrice'         => $this->numericOrNull($ip['child']['selling_price']    ?? null),
            'childTotal'         => $this->numericOrNull($ip['child']['amount']           ?? null),
            'childCostPrice'     => $this->numericOrNull($ip['child']['cost_price']       ?? null),
            'childCostTotal'     => $this->numericOrNull($ip['child']['total_cost_price'] ?? null),
        ]);
    }

    private function buildHotelItem(array $base, array $item, InclusivePackage $package): array
    {
        $roomId      = (int)($item['room_id'] ?? ($item['car_id'] ?? 0));
        $checkInDay  = isset($item['checkInDay'])
            ? $item['checkInDay']
            : $this->calculateDayNumber($item['checkin_date'] ?? null, $package);
        $checkOutDay = isset($item['checkOutDay'])
            ? $item['checkOutDay']
            : $this->calculateDayNumber($item['checkout_date'] ?? null, $package);

        return array_merge($base, [
            'type'           => 'Hotel',
            'name'           => $item['product_name']   ?? '',
            'checkIn'        => $item['checkin_date']   ?? null,
            'checkOut'       => $item['checkout_date']  ?? null,
            'checkin_date'   => $item['checkin_date']   ?? null,
            'checkout_date'  => $item['checkout_date']  ?? null,
            'checkInDay'     => $checkInDay,
            'checkOutDay'    => $checkOutDay,
            'rooms'          => (string)($item['quantity'] ?? 1),
            'nights'         => $item['days']           ?? null,
            'days'           => $item['days']           ?? null,
            'pricePerNight'  => (string)($item['selling_price'] ?? 0),
            'room_id'        => $roomId,
            'roomId'         => $roomId,
            'roomName'       => $item['item_name']      ?? '',
            'hotelId'        => $item['product_id']     ?? null,
            'hotelImage'     => $item['product_image']  ?? null,
            'totalDiscount'  => $item['totalDiscount']  ?? 0,
            'coveredCityIds' => $item['coveredCityIds'] ?? [],
            'dailyPricing'   => $item['dailyPricing']   ?? [],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // PRIVATE — MERGE CHANGED FIELDS
    // ══════════════════════════════════════════════════════════════

    private function mergeChangedFields(array $existing, array $changed, string $field): array
    {
        $updated = $existing;

        // ── Identity fields ──────────────────────────────────────────────────
        if (isset($changed['id']))           $updated['_booking_item_id'] = $changed['id'];
        if (isset($changed['product_id']))   $updated['product_id']       = $changed['product_id'];
        if (isset($changed['product_name'])) $updated['product_name']     = $changed['product_name'];
        if (isset($changed['product_image'])) $updated['product_image']   = $changed['product_image'];

        // ── Date fields — check both snake_case and camelCase ────────────────
        // FIX: was only checking camelCase with !empty(); now handles snake_case too
        if (isset($changed['service_date'])) {
            $updated['serviceDate']  = $changed['service_date'];
            $updated['service_date'] = $changed['service_date'];
        }
        if (isset($changed['serviceDate'])) {
            $updated['serviceDate']  = $changed['serviceDate'];
            $updated['service_date'] = $changed['serviceDate'];
        }

        // ── Price fields — FIX: use isset() not !empty() so 0 values are synced ──
        if (isset($changed['amount'])) {
            $updated['sellingPrice'] = (string)$changed['amount'];
        }
        if (isset($changed['total_cost_price'])) {
            $updated['costPrice'] = (string)$changed['total_cost_price'];
        }

        // ── Quantity — FIX: sync at base level so all product types get it ──────
        if (isset($changed['quantity'])) {
            $updated['quantity'] = $changed['quantity'];
        }

        // ── Type-specific merge ──────────────────────────────────────────────
        $updated = match ($field) {
            'van_tours'   => $this->mergeVanTourFields($updated, $changed),
            'attractions' => $this->mergeAttractionFields($updated, $changed),
            'hotels'      => $this->mergeHotelFields($updated, $changed),
            default       => $updated,
        };

        // ── Final safety cast — ensure sellingPrice/costPrice are always numeric strings ──
        if (isset($updated['sellingPrice'])) {
            $updated['sellingPrice'] = (string)(is_numeric($updated['sellingPrice'])
                ? $updated['sellingPrice'] + 0
                : 0);
        }
        if (isset($updated['costPrice'])) {
            $updated['costPrice'] = (string)(is_numeric($updated['costPrice'])
                ? $updated['costPrice'] + 0
                : 0);
        }

        return $updated;
    }

    private function mergeVanTourFields(array $updated, array $changed): array
    {
        if (isset($changed['car_id'])) {
            $updated['car_id'] = $changed['car_id'];
            $updated['carId']  = (int)$changed['car_id'];
        }
        if (isset($changed['car_list']))     $updated['car_list']  = $changed['car_list'];
        if (isset($changed['item_name'])) {
            $updated['carName']   = $changed['item_name'];
            $updated['item_name'] = $changed['item_name'];
        }
        if (isset($changed['quantity'])) {
            $updated['passengers'] = (string)$changed['quantity'];
            $updated['cars']       = (string)$changed['quantity'];
        }
        if (isset($changed['pickup_time'])) {
            $updated['pickup_time'] = $changed['pickup_time'];
            $updated['pickupTime']  = $changed['pickup_time'];
        }
        if (isset($changed['product_name'])) $updated['route']       = $changed['product_name'];
        if (isset($changed['vanTourType']))   $updated['vanTourType'] = $changed['vanTourType'];

        // FIX: was !empty() — now isset() so price 0 is also synced
        if (isset($changed['selling_price'])) {
            $updated['selling_price'] = (string)$changed['selling_price'];
            $updated['sellingPrice']  = (string)$changed['selling_price'];
        }

        return $updated;
    }

    private function mergeAttractionFields(array $updated, array $changed): array
    {
        if (isset($changed['car_id'])) {
            $updated['car_id']      = $changed['car_id'];
            $updated['variationId'] = $changed['car_id'];
        }
        if (isset($changed['car_list']))     $updated['car_list']  = $changed['car_list'];
        if (isset($changed['product_name'])) {
            $updated['name']        = $changed['product_name'];
            $updated['productName'] = $changed['product_name'];
        }
        if (isset($changed['item_name']))    $updated['item_name'] = $changed['item_name'];

        // FIX: quantity now also handled at base level, but keep here for adults sync
        if (isset($changed['quantity'])) {
            $updated['adults']   = (string)$changed['quantity'];
            $updated['quantity'] = (string)$changed['quantity'];
        }
        if (isset($changed['child_info']))   $updated['child_info'] = $changed['child_info'];

        // FIX: was !empty() — now isset()
        if (isset($changed['selling_price'])) $updated['selling_price'] = $changed['selling_price'];

        if (isset($changed['individual_pricing'])) {
            $ip = $this->normalizeIndividualPricing($changed['individual_pricing']);
            $updated['individual_pricing'] = $ip;

            // FIX: child qty comes from individual_pricing — always re-sync all child/adult fields
            $updated['adults']   = (string)($ip['adult']['quantity'] ?? $updated['adults'] ?? 0);
            $updated['children'] = (string)($ip['child']['quantity'] ?? $updated['children'] ?? 0);

            $updated['adultPrice']     = $this->numericOrNull($ip['adult']['selling_price']    ?? null);
            $updated['adultTotal']     = $this->numericOrNull($ip['adult']['amount']           ?? null);
            $updated['adultCostPrice'] = $this->numericOrNull($ip['adult']['cost_price']       ?? null);
            $updated['adultCostTotal'] = $this->numericOrNull($ip['adult']['total_cost_price'] ?? null);
            $updated['childPrice']     = $this->numericOrNull($ip['child']['selling_price']    ?? null);
            $updated['childTotal']     = $this->numericOrNull($ip['child']['amount']           ?? null);
            $updated['childCostPrice'] = $this->numericOrNull($ip['child']['cost_price']       ?? null);
            $updated['childCostTotal'] = $this->numericOrNull($ip['child']['total_cost_price'] ?? null);
        }

        return $updated;
    }

    private function mergeHotelFields(array $updated, array $changed): array
    {
        if (isset($changed['car_id'])) {
            $roomId             = (int)$changed['car_id'];
            $updated['car_id']  = $roomId;
            $updated['roomId']  = $roomId;
            $updated['room_id'] = $roomId;
        }
        if (isset($changed['car_list']))    $updated['car_list']  = $changed['car_list'];
        if (isset($changed['item_name'])) {
            $updated['roomName']  = $changed['item_name'];
            $updated['item_name'] = $changed['item_name'];
        }

        // FIX: quantity now also handled at base level, keep here for rooms sync
        if (isset($changed['quantity']))    $updated['rooms'] = (string)$changed['quantity'];

        if (isset($changed['checkin_date'])) {
            $updated['checkIn']      = $changed['checkin_date'];
            $updated['checkin_date'] = $changed['checkin_date'];
        }
        if (isset($changed['checkout_date'])) {
            $updated['checkOut']      = $changed['checkout_date'];
            $updated['checkout_date'] = $changed['checkout_date'];
        }

        // FIX: was isset() already — keep, but also ensure sellingPrice is updated
        if (isset($changed['selling_price'])) {
            $updated['pricePerNight'] = (string)$changed['selling_price'];
            $updated['selling_price'] = (string)$changed['selling_price'];
            $updated['sellingPrice']  = (string)$changed['selling_price']; // FIX: was missing
        }
        if (isset($changed['product_name'])) $updated['name'] = $changed['product_name'];
        if (isset($changed['days'])) {
            $updated['nights'] = $changed['days'];
            $updated['days']   = $changed['days'];
        }

        return $updated;
    }

    // ══════════════════════════════════════════════════════════════
    // PRIVATE — UTILITIES
    // ══════════════════════════════════════════════════════════════

    private function resolveField(string $productType): ?string
    {
        return match (true) {
            in_array($productType, [
                '1', '2', '3',
                'App\\Models\\PrivateVanTour',
                'App\\Models\\GroupTour',
                'App\\Models\\AirportPickup',
            ]) => 'van_tours',

            in_array($productType, [
                '4',
                'App\\Models\\EntranceTicket',
            ]) => 'attractions',

            in_array($productType, [
                '6',
                'App\\Models\\Hotel',
            ]) => 'hotels',

            default => null,
        };
    }

    private function isSameItem(array $packageItem, array $bookingItem): bool
    {
        if (isset($packageItem['_booking_item_id'], $bookingItem['id'])) {
            return (int)$packageItem['_booking_item_id'] === (int)$bookingItem['id'];
        }

        return isset($packageItem['product_id'], $bookingItem['product_id'])
            && (string)$packageItem['product_id'] === (string)$bookingItem['product_id'];
    }

    private function generateNextUid(InclusivePackage $package): string
    {
        $max      = 0;
        $allItems = array_merge(
            $package->attractions   ?? [],
            $package->hotels        ?? [],
            $package->van_tours     ?? [],
            $package->ordered_items ?? [],
        );

        foreach ($allItems as $item) {
            if (isset($item['_uid']) && preg_match('/_uid_(\d+)/', $item['_uid'], $m)) {
                $max = max($max, (int)$m[1]);
            }
        }

        return '_uid_' . ($max + 1);
    }

    /**
     * Calculate dayNumber from a date string relative to the package start_date.
     * start_date = 2026-03-03, service_date = 2026-03-03 → 1
     * start_date = 2026-03-03, service_date = 2026-03-04 → 2
     */
    private function calculateDayNumber(?string $date, InclusivePackage $package): ?int
    {
        if (!$date || !$package->start_date) return null;

        $start  = Carbon::parse($package->start_date)->startOfDay();
        $target = Carbon::parse($date)->startOfDay();

        if ($target->lt($start)) return null;

        return (int)$start->diffInDays($target) + 1;
    }

    private function normalizeIndividualPricing(mixed $ip): array
    {
        if (is_string($ip)) {
            return json_decode($ip, true) ?? [];
        }

        return is_array($ip) ? $ip : [];
    }

    /**
     * Cast to int/float, or return null for null / "null" / empty string.
     */
    private function numericOrNull(mixed $value): int|float|null
    {
        if ($value === null || $value === 'null' || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }
}
