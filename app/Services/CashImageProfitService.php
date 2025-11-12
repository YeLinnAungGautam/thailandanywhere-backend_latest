<?php

namespace App\Services;

use App\Models\CashImage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CashImageProfitService
{
    const VALID_INTERACT_BANK = [
        'personal', 'company', 'all', 'cash_at_office', 'to_money_changer', 'deposit_management', 'pay_to_driver'
    ];

    const VALID_RELATABLE_TYPES = [
        'App\Models\Booking',
        'App\Models\BookingItemGroup',
        'App\Models\CashBook',
    ];

    /**
     * Get unique CRM IDs with booking items
     */
    public function getUniqueCrmIds(Request $request)
    {
        try {
            $this->validateRequest($request);

            // Parse date range
            [$startDate, $endDate] = explode(',', $request->date);

            // Build items query with optional product_type filter
            $itemsQuery = function ($query) use ($request) {
                if ($request->filled('product_type')) {
                    $query->where('product_type', $request->product_type);
                }
            };

            // Query cash images
            $cashImages = CashImage::select([
                'id', 'date', 'relatable_id', 'relatable_type', 'interact_bank',
                'currency', 'sender', 'receiver', 'amount'
            ])
            ->where('interact_bank', $request->interact_bank)
            ->where('relatable_type', $request->relatable_type)
            ->whereDate('date', '>=', trim($startDate))
            ->whereDate('date', '<=', trim($endDate))
            ->with([
                'relatable.items' => $itemsQuery,
                'relatable.items.product',
                'cashBookings.items' => $itemsQuery,
                'cashBookings.items.product'
            ])
            ->get();

            // Process data
            $result = [];
            $seenCrmIds = [];
            $rowNumber = 1;

            foreach ($cashImages as $cashImage) {
                // Get booking from polymorphic or many-to-many
                $booking = $cashImage->relatable_id > 0
                    ? $cashImage->relatable
                    : $cashImage->cashBookings->first();

                if (!$booking || !$booking->crm_id) {
                    continue;
                }

                // Skip if no items (when filtered by product_type)
                if ($booking->items->isEmpty()) {
                    continue;
                }

                // Split CRM IDs if multiple
                $crmIds = array_filter(array_map('trim', explode(',', $booking->crm_id)));

                foreach ($crmIds as $crmId) {
                    if (in_array($crmId, $seenCrmIds)) {
                        continue;
                    }

                    $seenCrmIds[] = $crmId;

                    // Format booking items
                    $bookingItems = $booking->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'crm_id' => $item->crm_id ?? null,
                            'b_crm_id' => $item->booking->crm_id ?? null,
                            'product_name' => $item->product->name ?? $item->product_name ?? null,
                            'product_type' => class_basename($item->product_type),
                            'customer' => $item->booking->customer->name ?? null,
                            'service_date' => $item->service_date ?? null,
                            'quantity' => $item->quantity ?? 1,
                            'amount' => $item->amount ?? 0,
                            'cost' => $item->total_cost_price ?? 0,
                            'profit' => $item->amount - $item->total_cost_price,
                            'payment_status' => $item->payment_status,
                            'b_payment_status' => $item->booking->payment_status
                        ];
                    });

                    $result[] = [
                        'row_number' => $rowNumber++,
                        'crm_id' => $crmId,
                        'booking_id' => $booking->id,
                        'cash_image_id' => $cashImage->id,
                        'date' => $cashImage->date,
                        'amount' => $cashImage->amount,
                        'currency' => $cashImage->currency,
                        'sender' => $cashImage->sender,
                        'receiver' => $cashImage->receiver,
                        'booking_items' => $bookingItems,
                        'total_items' => $bookingItems->count(),
                    ];
                }
            }

            // Apply limit
            $limit = $request->input('limit', 500);
            $result = array_slice($result, 0, $limit);

            return [
                'success' => true,
                'data' => $result,
                'total_unique_crm_ids' => count($result),
                'filters_applied' => [
                    'date_range' => $request->date,
                    'interact_bank' => $request->interact_bank,
                    'relatable_type' => $request->relatable_type,
                    'product_type' => $request->product_type ?? 'all',
                ],
                'message' => 'Data retrieved successfully'
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            Log::error('Get CRM IDs Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ];
        }
    }

    private function validateRequest(Request $request)
    {
        $validator = Validator(
            $request->all(),
            [
                'date' => 'required|string',
                'interact_bank' => 'required|in:' . implode(',', self::VALID_INTERACT_BANK),
                'relatable_type' => 'required|in:' . implode(',', self::VALID_RELATABLE_TYPES),
                'product_type' => 'nullable|string', // Optional filter
                'limit' => 'nullable|integer|min:1|max:1000',
            ]
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }

        $dates = explode(',', $request->date);
        if (count($dates) !== 2 || !strtotime(trim($dates[0])) || !strtotime(trim($dates[1]))) {
            throw new InvalidArgumentException("Date must be format: YYYY-MM-DD,YYYY-MM-DD");
        }
    }
}
