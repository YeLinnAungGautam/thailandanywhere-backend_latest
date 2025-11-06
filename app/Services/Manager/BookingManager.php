<?php
namespace App\Services\Manager;

use Exception;
use App\Models\Airline;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Jobs\ArchiveSaleJob;
use App\Traits\ImageManager;
use App\Models\BookingReceipt;
use App\Models\InclusiveProduct;
use Illuminate\Support\Facades\DB;
use App\Jobs\UpdateBookingDatesJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\BookingRequest;
use App\Jobs\PersistBookingItemGroupJob;
use App\Action\UpsertBookingItemGroupAction;
use App\Models\CashImage;
use App\Models\Room;

class BookingManager
{
    use ImageManager;

    public static function createBookingWithReservation(BookingRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = [
                'customer_id' => $request->customer_id,
                'user_id' => $request->user_id,
                'sold_from' => $request->sold_from,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status,
                'payment_currency' => $request->payment_currency,
                'booking_date' => $request->booking_date,
                'bank_name' => $request->bank_name,
                'transfer_code' => $request->transfer_code,
                'money_exchange_rate' => $request->money_exchange_rate,
                'sub_total' => $request->sub_total,
                'grand_total' => $request->grand_total,
                'exclude_amount' => $request->exclude_amount,
                'deposit' => $request->deposit ?? 0,
                'balance_due' => $request->balance_due,
                'balance_due_date' => $request->balance_due_date,
                'discount' => $request->discount,
                'comment' => $request->comment,
                'is_past_info' => $request->is_past_info ?? false,
                'past_user_id' => $request->past_user_id,
                'past_crm_id' => $request->past_crm_id,
                'created_by' => Auth::id(),
                'reservation_status' => "awaiting",
                'payment_notes' => $request->payment_notes,
                'is_inclusive' => $request->is_inclusive ? $request->is_inclusive : 0,
                'inclusive_name' => $request->inclusive_name ?? null,
                'inclusive_description' => $request->inclusive_description ?? null,
                'inclusive_quantity' => $request->inclusive_quantity ?? null,
                'inclusive_rate' => $request->inclusive_rate ?? null,
                'inclusive_start_date' => $request->inclusive_start_date ?? null,
                'inclusive_end_date' => $request->inclusive_end_date ?? null,
            ];

            $booking = Booking::create($data);

            if ($request->receipt_image) {
                self::saveBookingReceipt($booking, $request->receipt_image);
            }

            foreach ($request->items as $key => $item) {
                self::saveBookingItem($booking, $key, $item, $request);
            }

            if ($booking->is_inclusive) {
                $booking_item_total = $booking->items->sum('amount');
                $inclusive_profit = $booking->grand_total - $booking_item_total;

                $booking->items()->create([
                    'crm_id' => $booking->crm_id . '_' . str_pad(count($request->items) + 1, 3, '0', STR_PAD_LEFT),
                    'product_type' => InclusiveProduct::class,
                    'product_id' => 0,
                    'is_inclusive' => true,
                    'amount' => $inclusive_profit,
                ]);
            }

            // Persist booking item groups
            if ($request->items) {
                PersistBookingItemGroupJob::dispatch($booking);
            }

            DB::commit();

            ArchiveSaleJob::dispatch($booking);

            UpdateBookingDatesJob::dispatch($booking->id);

            return $booking;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw new Exception($e->getMessage());
        }
    }

    public static function saveBookingItem(Booking $booking, $key, $item, $request)
    {
        $is_excluded = ($item['product_type'] == Airline::class) ? true : false;

        // Check allotment availability for rooms
        $is_allotment_have = false;
        if (isset($item['room_id']) && isset($item['product_id']) && isset($item['checkin_date']) && isset($item['checkout_date'])) {
            $is_allotment_have = self::checkRoomAllotment(
                $item['product_id'], // hotel/product ID
                $item['room_id'],
                $item['checkin_date'],
                $item['checkout_date'],
                $item['quantity'] ?? 1
            );
        }

        $data = [
            'booking_id' => $booking->id,
            'crm_id' => $booking->crm_id . '_' . str_pad($key + 1, 3, '0', STR_PAD_LEFT),
            'product_type' => $item['product_type'],
            'room_number' => $item['room_number'] ?? null,
            'product_id' => $item['product_id'],
            'is_excluded' => $is_excluded,
            'is_allowment_have' => $is_allotment_have, // Add this field
            // Save these fields directly without isset check
            'car_id' => $item['car_id'] ?? null,
            'room_id' => $item['room_id'] ?? null,
            'ticket_id' => $item['ticket_id'] ?? null,
            'variation_id' => $item['variation_id'] ?? null,
            'service_date' => $item['service_date'] ?? null,
            'quantity' => $item['quantity'] ?? null,
            'total_guest' => $item['total_guest'] ?? null,
            'duration' => $item['duration'] ?? null,
            'selling_price' => $item['selling_price'] ?? null,
            'cost_price' => $item['cost_price'] ?? null,
            'total_cost_price' => $item['total_cost_price'] ?? null,
            'payment_method' => $item['payment_method'] ?? null,
            'payment_status' => $item['payment_status'] ?? 'not_paid',
            'exchange_rate' => $item['exchange_rate'] ?? null,
            'comment' => $item['comment'] ?? null,
            'amount' => $item['amount'] ?? null,
            'discount' => $item['discount'] ?? null,
            'days' => $item['days'] ?? null,
            'special_request' => $item['special_request'] ?? null,
            'route_plan' => $item['route_plan'] ?? null,
            'pickup_location' => $item['pickup_location'] ?? null,
            'pickup_time' => $item['pickup_time'] ?? null,
            'dropoff_location' => $item['dropoff_location'] ?? null,
            'checkin_date' => $item['checkin_date'] ?? null,
            'checkout_date' => $item['checkout_date'] ?? null,
            'reservation_status' => $item['reservation_status'] ?? "awaiting",
            'slip_code' => $request->slip_code,
            'is_inclusive' => $request->is_inclusive ? $request->is_inclusive : 0,
            'individual_pricing' => isset($item['individual_pricing']) ? json_encode($item['individual_pricing']) : null,
            'cancellation' => $item['cancellation'] ?? null,
            'addon' => isset($item['addon']) ? json_encode($item['addon']) : null,
        ];

        if (isset($request->items[$key]['customer_attachment'])) {
            $attachment = $request->items[$key]['customer_attachment'];

            $fileData = upload_file($attachment, 'attachments/');
            $data['customer_attachment'] = $fileData['fileName'];
        }

        if (isset($request->items[$key]['receipt_image'])) {
            $receiptImage = $request->items[$key]['receipt_image'];

            if ($receiptImage) {
                $fileData = upload_file($receiptImage, 'images/');
                $data['receipt_image'] = $fileData['fileName'];
            }
        }

        if (isset($request->items[$key]['confirmation_letter'])) {
            $file = $request->items[$key]['confirmation_letter'];

            if ($file) {
                $fileData = upload_file($file, 'files/');
                $data['confirmation_letter'] = $fileData['fileName'];
            }
        }

        BookingItem::create($data);
    }

    // app/Services/Manager/BookingManager.php

    private static function checkRoomAllotment($productId, $roomId, $checkinDate, $checkoutDate, $quantity = 1)
    {
        try {
            // Get the room
            $room = Room::find($roomId);

            if (!$room) {
                return false;
            }

            // Check if the hotel has a partner
            $partner = $room->hotel->partners->first();

            if (!$partner) {
                return false; // No partner means no allotment system
            }

            // Use PartnerRoomRateService to check allotment
            $roomRateService = new \App\Services\PartnerRoomRateService($partner->id, $roomId);

            // ✅ Pass quantity to check
            $isIncomplete = $roomRateService->isIncompleteAllotment($checkinDate, $checkoutDate, $quantity);

            // If it's NOT incomplete, then allotment is available
            return !$isIncomplete;

        } catch (\Exception $e) {
            Log::error('Error checking room allotment: ' . $e->getMessage());
            return false;
        }
    }

    public static function persistBookingItemGroup(Booking $booking)
    {
        UpsertBookingItemGroupAction::execute($booking);
    }

    private static function saveBookingReceipt(Booking $booking, array $receipt_images)
    {
        foreach ($receipt_images as $index => $receipt) {
            // Debug log to see what we're receiving
            Log::info("Processing receipt index: $index", ['receipt_data' => $receipt]);

            $is_internal_transfer = $receipt['is_internal_transfer'] ?? false;

            Log::info("Is internal transfer: " . ($is_internal_transfer ? 'yes' : 'no'));

            // Handle internal transfer
            if ($is_internal_transfer) {
                $exchange_rate = $receipt['exchange_rate'] ?? 1;
                $note = $receipt['note'] ?? null;

                // Create internal transfer record
                $internalTransfer = \App\Models\InternalTransfer::create([
                    'exchange_rate' => $exchange_rate,
                    'notes' => $note,
                ]);

                // Handle "from" files (source)
                if (isset($receipt['from_files']) && is_array($receipt['from_files'])) {
                    Log::info("Processing from_files", ['count' => count($receipt['from_files'])]);

                    foreach ($receipt['from_files'] as $fromIndex => $fromFile) {
                        Log::info("Processing from_file index: $fromIndex", ['from_file_keys' => array_keys($fromFile)]);

                        if (!isset($fromFile['file'])) {
                            Log::warning("No file in from_files at index $fromIndex");
                            continue;
                        }

                        // Prepare relatables data
                        $relatables = [];

                        // If this receipt is for multiple bookings
                        if (isset($receipt['relatables']) && is_array($receipt['relatables'])) {
                            foreach ($receipt['relatables'] as $relatedBooking) {
                                $relatables[] = [
                                    'relatable_id' => $relatedBooking['booking_id'],
                                    'relatable_type' => Booking::class,
                                    'deposit' => $relatedBooking['deposit'] ?? 0,
                                ];
                            }
                        }

                        $fileDataFrom = upload_file($fromFile['file'], 'images/');

                        $cashImageFrom = CashImage::create([
                            'relatable_id' => $booking->id,
                            'relatable_type' => Booking::class,
                            'date' => $fromFile['date'] ?? now(),
                            'sender' => $fromFile['sender'] ?? null,
                            'receiver' => $fromFile['receiver'] ?? null,
                            'amount' => $fromFile['amount'] ?? 0,
                            'currency' => $fromFile['currency'] ?? 'THB',
                            'interact_bank' => $fromFile['interact_bank'] ?? 'personal',
                            'image' => $fileDataFrom['fileName'],
                            'internal_transfer' => true,
                            'relatables' => $relatables
                        ]);

                        $internalTransfer->cashImagesFrom()->attach($cashImageFrom->id, [
                            'direction' => 'from'
                        ]);
                    }
                }

                // Handle "to" files (destination)
                if (isset($receipt['to_files']) && is_array($receipt['to_files'])) {
                    Log::info("Processing to_files", ['count' => count($receipt['to_files'])]);

                    foreach ($receipt['to_files'] as $toIndex => $toFile) {
                        Log::info("Processing to_file index: $toIndex", ['to_file_keys' => array_keys($toFile)]);

                        if (!isset($toFile['file'])) {
                            Log::warning("No file in to_files at index $toIndex");
                            continue;
                        }

                        // Prepare relatables data
                        $relatables = [];

                        // If this receipt is for multiple bookings
                        if (isset($receipt['relatables']) && is_array($receipt['relatables'])) {
                            foreach ($receipt['relatables'] as $relatedBooking) {
                                $relatables[] = [
                                    'relatable_id' => $relatedBooking['booking_id'],
                                    'relatable_type' => Booking::class,
                                    'deposit' => $relatedBooking['deposit'] ?? 0,
                                ];
                            }
                        }

                        $fileDataTo = upload_file($toFile['file'], 'images/');

                        $cashImageTo = CashImage::create([
                            'relatable_id' => $booking->id,
                            'relatable_type' => Booking::class,
                            'date' => $toFile['date'] ?? now(),
                            'sender' => $toFile['sender'] ?? null,
                            'receiver' => $toFile['receiver'] ?? null,
                            'amount' => $toFile['amount'] ?? 0,
                            'currency' => $toFile['currency'] ?? 'THB',
                            'interact_bank' => $toFile['interact_bank'] ?? 'personal',
                            'image' => $fileDataTo['fileName'],
                            'internal_transfer' => true,
                            'relatables' => $relatables
                        ]);

                        $internalTransfer->cashImagesTo()->attach($cashImageTo->id, [
                            'direction' => 'to'
                        ]);
                    }
                }
            } else {
                // Regular cash image
                Log::info("Processing regular cash image", ['receipt_keys' => array_keys($receipt)]);

                if (!isset($receipt['file'])) {
                    Log::warning("No file in regular receipt");
                    continue;
                }

                $image = $receipt['file'];
                $amount = $receipt['amount'] ?? 0;
                $bank_name = $receipt['bank_name'] ?? null;
                $date = $receipt['date'] ?? now();
                $is_corporate = $receipt['is_corporate'] ?? false;
                $note = $receipt['note'] ?? null;
                $sender = $receipt['sender'] ?? null;
                $reciever = $receipt['reciever'] ?? null;
                $interact_bank = $receipt['interact_bank'] ?? 'personal';
                $currency = $receipt['currency'] ?? 'THB';

                // Prepare relatables data
                $relatables = [];

                // If this receipt is for multiple bookings
                if (isset($receipt['relatables']) && is_array($receipt['relatables'])) {
                    foreach ($receipt['relatables'] as $relatedBooking) {
                        $relatables[] = [
                            'relatable_id' => $relatedBooking['booking_id'],
                            'relatable_type' => Booking::class,
                            'deposit' => $relatedBooking['deposit'] ?? 0,
                        ];
                    }
                }

                $fileData = upload_file($image, 'images/');

                CashImage::create([
                    'relatable_id' => $booking->id,
                    'relatable_type' => Booking::class,
                    'date' => $date,
                    'sender' => $sender,
                    'receiver' => $reciever,
                    'amount' => $amount,
                    'currency' => $currency,
                    'interact_bank' => $interact_bank,
                    'image' => $fileData['fileName'],
                    'internal_transfer' => false,
                    'relatables' => $relatables
                ]);
            }
        }
    }
}
