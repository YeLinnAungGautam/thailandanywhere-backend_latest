<?php
// app/Services/BookingFinancialSummaryService.php

namespace App\Services;

use App\Models\CashImage;
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
    public function getMonthlyFinancialSummary(string $dateRangeString)
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

            $query = CashImage::query()->with(['relatable']);
            $query->whereBetween('date', [$startDate, $endDate]);
            $query->where('interact_bank','company');
            $query->where('relatable_type', 'App\Models\Booking');

            $cashImages = $query->get();

            $total_vat = 0;
            $total_commission = 0;
            $total_net_vat = 0;

            foreach ($cashImages as $cashImage) {
                $booking = $cashImage->relatable;

                if ($booking) {
                    $total_vat += $booking->output_vat ?? 0;
                    $total_commission += $booking->commission ?? 0;
                    $total_net_vat += ($booking->commission ?? 0) - ($booking->commission ?? 0) / 1.07;
                }
            }

            return [
                'success' => true,
                'total_vat' => $total_vat,
                'total_commission' => $total_commission,
                'total_net_vat' => $total_net_vat,
                'message' => 'Financial summary calculated successfully',
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'total_vat' => 0,
                'total_commission' => 0,
                'total_net_vat' => 0,
                'message' => 'Validation Error: ' . $e->getMessage(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'total_vat' => 0,
                'total_commission' => 0,
                'total_net_vat' => 0,
                'message' => 'An error occurred during financial summary calculation: ' . $e->getMessage(),
            ];
        }
    }
}
