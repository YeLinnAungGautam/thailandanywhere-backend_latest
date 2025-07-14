<?php

// app/Services/BookingFinancialSummaryService.php

namespace App\Services;

use App\Models\BookingItem;
use App\Models\Booking; // Make sure Booking model is imported
use App\Models\BookingItemGroup; // Make sure BookingItemGroup model is imported
use Carbon\Carbon;
use Exception;
use InvalidArgumentException; // Import for validation errors

class BookingFinancialService // Renamed from BookingFinancialSummaryService in previous response, keeping consistent with your provided code
{
    /**
     * Calculate financial summary for a given date range based on specific BookingItem criteria.
     *
     * @param string $dateRangeString A comma-separated string of start and end dates (e.g., 'YYYY-MM-DD,YYYY-MM-DD')
     * @return array
     */
    public function getMonthlyFinancialSummary(string $dateRangeString): array
    {
        try {
            // Parse the date range string
            $dates = explode(',', $dateRangeString);
            if (count($dates) !== 2) {
                throw new InvalidArgumentException("Invalid date range format. Expected 'YYYY-MM-DD,YYYY-MM-DD'.");
            }

            $startDate = Carbon::parse(trim($dates[0]))->startOfDay();
            $endDate = Carbon::parse(trim($dates[1]))->endOfDay();

            // Basic validation for valid dates
            if (!$startDate || !$endDate || $startDate->greaterThan($endDate)) {
                throw new InvalidArgumentException("Invalid start or end date in the range.");
            }

            // 1. Filter BookingItems based on criteria
            $filteredBookingItems = BookingItem::query()
                // Filter by service_date within the parsed date range
                ->whereBetween('service_date', [$startDate, $endDate])
                // Filter by booking payment_status 'fully_paid'
                ->whereHas('booking', function ($query) {
                    $query->where('payment_status', 'fully_paid');
                })
                // Filter by product_type (Hotel or EntranceTicket)
                ->whereIn('product_type', [
                    \App\Models\Hotel::class,
                    \App\Models\EntranceTicket::class,
                ])
                // Filter by booking is_inclusive != 1
                ->whereHas('booking', function ($query) {
                    $query->where('is_inclusive', '!=', 1);
                })
                // Eager load necessary relationships for calculations and checks
                ->with([
                    'booking', // For payment_status and is_inclusive
                    'group.customerDocuments', // For checking booking_confirm_letter
                ])
                ->get();

            $totalCommission = 0;
            $currentOutputVat = 0; // For items with booking_confirm_letter
            $totalOutputVatNotConfirmed = 0; // For items WITHOUT booking_confirm_letter
            $reallyOutputVat = 0; // This should be the actual VAT we need to pay

            // 2. Iterate through filtered BookingItems for calculations
            foreach ($filteredBookingItems as $item) {
                // Sum up commission for all filtered items
                $totalCommission += $item->commission ?? 0;

                // Check for booking_confirm_letter in the item's group
                $hasConfirmLetter = false;
                if ($item->group) {
                    $hasConfirmLetter = $item->group->customerDocuments
                                                    ->where('type', 'booking_confirm_letter')
                                                    ->isNotEmpty();
                }

                // Get amounts
                $amount = $item->amount ?? 0;
                $costPrice = $item->total_cost_price ?? 0;
                $profit = $amount - $costPrice;

                if ($hasConfirmLetter) {
                    // If booking_confirm_letter exists: calculate based on profit margin
                    // output_vat = profit - (profit / 1.07)
                    $calculatedVat = $profit - ($profit / 1.07);
                    $currentOutputVat += $calculatedVat;
                } else {
                    // If booking_confirm_letter does NOT exist: use item's own output_vat field
                    $currentOutputVat += $item->output_vat ?? 0;
                }

                // Always add to total_output_vat_not_confirmed (this represents all items' output_vat)
                $totalOutputVatNotConfirmed += $item->output_vat ?? 0;

                // FIXED: really_output_vat should be the VAT on cost price only (what we actually need to pay)
                // This is VAT on our expenses/costs, not on profit
                if ($costPrice > 0) {
                    // VAT on cost price = cost_price - (cost_price / 1.07)
                    // This represents the actual VAT we paid on our expenses
                    $reallyOutputVat += $profit - ($profit / 1.07);
                }
            }

            return [
                'success' => true,
                'total_commission' => round($totalCommission, 2),
                'really_output_vat' => round($reallyOutputVat, 2), // VAT on our costs/expenses
                'current_output_vat' => round($currentOutputVat, 2), // VAT we should collect/report
                'total_output_vat' => round($totalOutputVatNotConfirmed, 2), // All items' output_vat
                'message' => 'Financial summary calculated successfully for ' . $startDate->format('d F Y') . ' to ' . $endDate->format('d F Y'),
                // Add some debug info
                'debug' => [
                    'total_items_processed' => $filteredBookingItems->count(),
                    'date_range' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
                ]
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'total_commission' => 0,
                'really_output_vat' => 0,
                'current_output_vat' => 0,
                'total_output_vat' => 0,
                'message' => 'Validation Error: ' . $e->getMessage(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'total_commission' => 0,
                'really_output_vat' => 0,
                'current_output_vat' => 0,
                'total_output_vat' => 0,
                'message' => 'An error occurred during financial summary calculation: ' . $e->getMessage(),
            ];
        }
    }
}
