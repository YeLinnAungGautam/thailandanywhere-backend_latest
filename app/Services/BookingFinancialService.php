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

            // Get all cash images in the date range with company interact_bank
            $allCashImages = CashImage::query()
                ->with(['relatable'])
                ->whereBetween('date', [$startDate, $endDate])
                ->where('interact_bank', 'company')
                ->get();

            $cashImageAll = CashImage::query()
            ->with(['relatable'])
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

            // Separate income and expense
            $incomeCashImages = $cashImageAll->where('relatable_type', 'App\Models\Booking')->where('currency', 'THB');
            $incomeCashImagesMMK = $cashImageAll->where('relatable_type', 'App\Models\Booking')->where('currency', 'MMK');
            $expenseCashImages = $cashImageAll->where('relatable_type', '!=', 'App\Models\Booking',)->where('currency', 'THB');
            $expenseCashImagesMMK = $cashImageAll->where('relatable_type', '!=', 'App\Models\Booking',)->where('currency', 'MMK');

            // Calculate totals
            $total_income = $incomeCashImages->sum('amount');
            $total_expense = $expenseCashImages->sum('amount');
            $total_income_mmk = $incomeCashImagesMMK->sum('amount');
            $total_expense_mmk = $expenseCashImagesMMK->sum('amount');

            $total_vat = 0;
            $total_commission = 0;
            $total_net_vat = 0;

            foreach ($incomeCashImages as $cashImage) {
                $booking = $cashImage->relatable;

                if ($booking) {
                    $total_vat += $booking->output_vat ?? 0;
                    $total_commission += $booking->commission ?? 0;
                    $total_net_vat += ($booking->commission ?? 0) - ($booking->commission ?? 0) / 1.07;
                }
            }

            return [
                'success' => true,
                'total_income' => $total_income,
                'total_expense' => $total_expense,
                'total_income_mmk' => $total_income_mmk,
                'total_expense_mmk' => $total_expense_mmk,
                'total_vat' => $total_vat,
                'total_commission' => $total_commission,
                'total_net_vat' => $total_net_vat,
                'message' => 'Financial summary calculated successfully',
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'total_income' => 0,
                'total_expense' => 0,
                'total_income_mmk' => 0,
                'total_expense_mmk' => 0,
                'total_vat' => 0,
                'total_commission' => 0,
                'total_net_vat' => 0,
                'message' => 'Validation Error: ' . $e->getMessage(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'total_income' => 0,
                'total_expense' => 0,
                'total_income_mmk' => 0,
                'total_expense_mmk' => 0,
                'total_vat' => 0,
                'total_commission' => 0,
                'total_net_vat' => 0,
                'message' => 'An error occurred during financial summary calculation: ' . $e->getMessage(),
            ];
        }
    }
}
