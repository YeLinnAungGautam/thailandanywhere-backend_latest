<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingVatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $bookingId;
    public function __construct(int $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $booking = Booking::with('items')->find($this->bookingId);

            if (!$booking) {
                return;
            }

            DB::beginTransaction();

            $this->calculateBookingVatAndCommission($booking);

            DB::commit();

            // Log::info("Successfully calculated VAT for booking ID: {$this->bookingId}");

        } catch (\Exception $e) {
            //throw $th;
            DB::rollBack();
            Log::error($e);
        }
    }

    private function calculateBookingVatAndCommission(Booking $booking): void
    {
        // Validate booking data
        if (!$this->validateBookingData($booking)) {
            return;
        }

        $grandTotal = $this->sanitizeAmount($booking->grand_total);
        $totalItemCost = 0;

        // Calculate items first
        foreach ($booking->items as $item) {
            $this->calculateItemVatAndCommission($item);
            $totalItemCost += $this->sanitizeAmount($item->total_cost_price ?? 0);
        }

        // Calculate booking totals
        $vatAmount = $grandTotal * 0.07; // 7% VAT
        $profit = $grandTotal - $totalItemCost;
        $commission = $profit > 0 ? $profit / 2 : 0;

        // Update booking
        $booking->update([
            'output_vat' => round($vatAmount, 2),
            'commission' => round($commission, 2),
        ]);

        // Log::info("Updated booking ID {$booking->id}: VAT={$vatAmount}, Commission={$commission}");
    }

    private function calculateItemVatAndCommission($item): void
    {
        try {
            if (!$this->validateItemData($item)) {
                return;
            }

            $costPrice = $this->sanitizeAmount($item->total_cost_price ?? 0);
            $amount = $this->sanitizeAmount($item->amount ?? 0);

            $vatAmount = $costPrice * 0.07; // 7% VAT on cost price
            $profit = $amount - $costPrice;
            $commission = $profit > 0 ? $profit / 2 : 0;

            $item->update([
                'output_vat' => round($vatAmount, 2),
                'commission' => round($commission, 2),
            ]);

            Log::debug("Updated item ID {$item->id}: VAT={$vatAmount}, Commission={$commission}");

        } catch (\Exception $e) {
            Log::error("Error calculating VAT for item ID {$item->id}: " . $e->getMessage());

            throw $e;
        }
    }

    private function validateBookingData(Booking $booking): bool
    {
        if (is_null($booking->grand_total) || $booking->grand_total === '') {
            Log::warning("Booking ID {$booking->id}: Missing grand_total");

            return false;
        }

        $grandTotal = $this->sanitizeAmount($booking->grand_total);
        if ($grandTotal <= 0) {
            Log::warning("Booking ID {$booking->id}: Invalid grand_total ({$grandTotal})");

            return false;
        }

        return true;
    }

    private function validateItemData($item): bool
    {
        $costPrice = $this->sanitizeAmount($item->total_cost_price ?? 0);
        $amount = $this->sanitizeAmount($item->amount ?? 0);

        if ($costPrice < 0 || $amount < 0) {
            Log::warning("Item ID {$item->id}: Negative values");

            return false;
        }

        return true;
    }

    private function sanitizeAmount($value): float
    {
        if (is_null($value) || $value === '') {
            return 0.0;
        }

        // Remove commas and convert to float
        $cleaned = str_replace([',', ' '], '', (string) $value);

        return (float) $cleaned;
    }
}
