<?php

namespace App\Services;

use App\Models\BookingItem;
use Carbon\Carbon;

class ChartOfAccountCalculationService
{
    /**
     * Calculate totals for specific account codes based on booking data
     */
    public function calculateAccountCodeTotals($item, $month)
    {
        $productTypeMap = [
            '1-3000-01' => 'App\\Models\\PrivateVanTour',
            '1-3000-02' => 'App\\Models\\Hotel',
            '1-3000-03' => 'App\\Models\\EntranceTicket'
        ];

        $productType = $productTypeMap[$item->account_code] ?? null;

        if (!$productType) {
            return $item;
        }

        // Calculate overdue balance due total for overdue balance_due_date and not_paid status
        $item->over_balance_due_total = $this->calculateOverdueAmount($productType, $month);

        return $item;
    }

    public function getItemOverBalanceDue($productType, $month)
    {
        $currentDate = Carbon::now()->startOfDay();

        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        return BookingItem::where('product_type', $productType)
            ->whereNotNull('amount')
            ->whereHas('booking', function ($query) use ($startDate, $endDate, $currentDate) {
                $query->where('is_inclusive', '!=', 1)
                    ->where('payment_status', 'not_paid')
                    ->where('balance_due_date', '<', $currentDate)
                    ->whereBetween('booking_date', [$startDate, $endDate]);
            });
    }

    /**
     * Calculate price connection totals
     */
    public function calculatePriceConnectionTotals($item, $month)
    {
        $totalAmount = 0;
        $verifiedAmount = 0;
        $unverifiedAmount = 0;
        $pendingAmount = 0;

        // For VanTour connection
        if ($item->connection === 'vantour') {
            $totalAmount = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'amount', 'fully_paid');
            $verifiedAmount = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'amount', 'fully_paid', 'verified');
            $unverifiedAmount = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'amount', 'fully_paid', 'unverified');
            $pendingAmount = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'amount', 'fully_paid', 'pending');
        }
        // For Hotel connection
        elseif ($item->connection === 'hotel') {
            $totalAmount = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'amount', 'fully_paid');
            $verifiedAmount = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'amount', 'fully_paid', 'verified');
            $unverifiedAmount = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'amount', 'fully_paid', 'unverified');
            $pendingAmount = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'amount', 'fully_paid', 'pending');
        }
        // For Ticket connection
        elseif ($item->connection === 'ticket') {
            $totalAmount = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'amount', 'fully_paid');
            $verifiedAmount = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'amount', 'fully_paid', 'verified');
            $unverifiedAmount = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'amount', 'fully_paid', 'unverified');
            $pendingAmount = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'amount', 'fully_paid', 'pending');
        }

        $item->total_amount = $totalAmount;
        $item->verified_amount = $verifiedAmount;
        $item->unverified_amount = $unverifiedAmount;
        $item->pending_amount = $pendingAmount;

        return $item;
    }

    /**
     * Calculate expense connection totals
     */
    public function calculateExpenseConnectionTotals($item, $month)
    {
        $totalCostPrice = 0;
        $verifiedCostPrice = 0;
        $unverifiedCostPrice = 0;
        $pendingCostPrice = 0;

        // For VanTour connection
        if ($item->connection === 'vantour') {
            $totalCostPrice = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'total_cost_price', 'fully_paid');
            $verifiedCostPrice = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'total_cost_price', 'fully_paid', 'verified');
            $unverifiedCostPrice = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'total_cost_price', 'fully_paid', 'unverified');
            $pendingCostPrice = $this->calculateTotalForProductType('App\\Models\\PrivateVanTour', $month, 'total_cost_price', 'fully_paid', 'pending');
        }
        // For Hotel connection
        elseif ($item->connection === 'hotel') {
            $totalCostPrice = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'total_cost_price', 'fully_paid');
            $verifiedCostPrice = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'total_cost_price', 'fully_paid', 'verified');
            $unverifiedCostPrice = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'total_cost_price', 'fully_paid', 'unverified');
            $pendingCostPrice = $this->calculateTotalForProductType('App\\Models\\Hotel', $month, 'total_cost_price', 'fully_paid', 'pending');
        }
        // For Ticket connection
        elseif ($item->connection === 'ticket') {
            $totalCostPrice = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'total_cost_price', 'fully_paid');
            $verifiedCostPrice = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'total_cost_price', 'fully_paid', 'verified');
            $unverifiedCostPrice = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'total_cost_price', 'fully_paid', 'unverified');
            $pendingCostPrice = $this->calculateTotalForProductType('App\\Models\\EntranceTicket', $month, 'total_cost_price', 'fully_paid', 'pending');
        }

        $item->total_cost_amount = $totalCostPrice;
        $item->verified_cost_price = $verifiedCostPrice;
        $item->unverified_cost_price = $unverifiedCostPrice;
        $item->pending_cost_price = $pendingCostPrice;

        return $item;
    }

    /**
     * Calculate overdue amount for specific product type
     * Where balance_due_date is past and payment_status is not_paid
     *
     * @param  string $productType The fully qualified class name of the product type
     * @param  string $month       The month in YYYY-MM format (for reference, but we check all overdue)
     * @return float  The total overdue amount
     */
    private function calculateOverdueAmount($productType, $month)
    {
        $currentDate = Carbon::now()->startOfDay();

        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $query = BookingItem::where('product_type', $productType)
            ->whereNotNull('amount')
            // ->whereBetween('service_date', [$startDate, $endDate])
            ->whereHas('booking', function ($query) use ($startDate, $endDate, $currentDate) {
                $query->where('is_inclusive', '!=', 1)
                    ->where('payment_status', 'not_paid')
                    ->where('balance_due_date', '<', $currentDate)
                    ->whereBetween('booking_date', [$startDate, $endDate]);
            });

        return $query->sum('amount') ?? 0;
    }

    /**
     * Calculate total for a specific product type in the given month based on booking payment status and verify status
     */
    private function calculateTotalForProductType($productType, $month, $field, $paymentStatus, $verifyStatus = null)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $query = BookingItem::where('product_type', $productType)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull($field)
            ->whereHas('booking', function ($query) use ($paymentStatus, $verifyStatus) {
                // Only include booking items whose related booking is not inclusive
                $query->where('is_inclusive', '!=', 1);

                // Filter by booking payment status
                $query->where('payment_status', $paymentStatus);

                // Filter by verify status if provided
                if ($verifyStatus !== null) {
                    $query->where('verify_status', $verifyStatus);
                }
            });

        return $query->sum($field) * 1 ?? 0;
    }
}
