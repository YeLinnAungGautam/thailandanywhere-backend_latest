<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateAutoVatForBooking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:auto-calculate-vat {--booking-id= : Process specific booking ID} {--chunk=50 : Number of bookings to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate VAT and commission for bookings directly (no job queue)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting VAT calculation for bookings...');

        $startTime = microtime(true);
        $processedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        try {
            if ($bookingId = $this->option('booking-id')) {
                // Process single booking
                $this->processSingleBooking($bookingId);
            } else {
                // Process all bookings
                $this->processAllBookings($processedCount, $failedCount, $skippedCount);
            }

            $duration = round(microtime(true) - $startTime, 2);

            // Show summary
            $this->showSummary($processedCount, $failedCount, $skippedCount, $duration);

        } catch (\Exception $e) {
            $this->error("❌ Critical error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Process a single booking by ID
     */
    private function processSingleBooking($bookingId)
    {
        $this->info("🔍 Processing booking ID: {$bookingId}");

        $booking = Booking::with('items')->find($bookingId);

        if (!$booking) {
            $this->error("❌ Booking not found: {$bookingId}");
            return;
        }

        $this->displayBookingInfo($booking);

        if ($this->calculateBookingVatAndCommission($booking)) {
            $booking->refresh();
            $this->info("✅ Successfully calculated VAT and commission");
            $this->info("💰 Output VAT: " . number_format($booking->output_vat, 2));
            $this->info("💼 Commission: " . number_format($booking->commission, 2));
        } else {
            $this->error("❌ Failed to calculate VAT for this booking");
        }
    }

    /**
     * Process all bookings with progress bar
     */
    private function processAllBookings(&$processedCount, &$failedCount, &$skippedCount)
    {
        $totalBookings = Booking::count();
        $chunkSize = (int) $this->option('chunk');

        $this->info("📊 Found {$totalBookings} bookings to process");
        $this->info("⚡ Processing in chunks of {$chunkSize}");

        if ($totalBookings === 0) {
            $this->warn('⚠️  No bookings found in database');
            return;
        }

        // Create progress bar
        $progressBar = $this->output->createProgressBar($totalBookings);
        $progressBar->setFormat('detailed');

        // Process bookings in chunks
        Booking::with('items')->chunk($chunkSize, function ($bookings) use ($progressBar, &$processedCount, &$failedCount, &$skippedCount) {
            foreach ($bookings as $booking) {
                try {
                    if ($this->calculateBookingVatAndCommission($booking)) {
                        $processedCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->newLine();
                    $this->error("❌ Failed booking ID {$booking->id}: " . $e->getMessage());
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);
    }

    /**
     * Calculate VAT and commission for a booking
     */
    private function calculateBookingVatAndCommission(Booking $booking): bool
    {
        try {
            // Validate booking data
            if (!$this->validateBookingData($booking)) {
                return false;
            }

            DB::beginTransaction();

            $grandTotal = $this->sanitizeAmount($booking->grand_total);
            $totalItemCost = 0;
            $itemsProcessed = 0;

            // Process booking items first
            if ($booking->items && $booking->items->count() > 0) {
                foreach ($booking->items as $item) {
                    if ($this->calculateItemVatAndCommission($item)) {
                        $totalItemCost += $this->sanitizeAmount($item->total_cost_price ?? 0);
                        $itemsProcessed++;
                    }
                }
            }

            // Calculate booking totals
            $vatAmount = $grandTotal * 0.07; // 7% VAT
            $profit = $grandTotal - $totalItemCost;
            $commission = $profit > 0 ? $profit / 2 : 0;

            // Update booking
            $updateResult = $booking->update([
                'output_vat' => round($vatAmount, 2),
                'commission' => round($commission, 2),
            ]);

            if (!$updateResult) {
                throw new \Exception("Failed to update booking record");
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate VAT and commission for a booking item
     */
    private function calculateItemVatAndCommission($item): bool
    {
        try {
            if (!$this->validateItemData($item)) {
                return false;
            }

            $costPrice = $this->sanitizeAmount($item->total_cost_price ?? 0);
            $amount = $this->sanitizeAmount($item->amount ?? 0);

            $vatAmount = $costPrice * 0.07; // 7% VAT on cost price
            $profit = $amount - $costPrice;
            $commission = $profit > 0 ? $profit / 2 : 0;

            $updateResult = $item->update([
                'output_vat' => round($vatAmount, 2),
                'commission' => round($commission, 2),
            ]);

            return $updateResult;

        } catch (\Exception $e) {
            throw new \Exception("Item calculation failed: " . $e->getMessage());
        }
    }

    /**
     * Validate booking data
     */
    private function validateBookingData(Booking $booking): bool
    {
        if (is_null($booking->grand_total) || $booking->grand_total === '') {
            $this->warn("⚠️  Booking ID {$booking->id}: Missing grand_total, skipping");
            return false;
        }

        $grandTotal = $this->sanitizeAmount($booking->grand_total);
        if ($grandTotal <= 0) {
            $this->warn("⚠️  Booking ID {$booking->id}: Invalid grand_total ({$grandTotal}), skipping");
            return false;
        }

        return true;
    }

    /**
     * Validate item data
     */
    private function validateItemData($item): bool
    {
        $costPrice = $this->sanitizeAmount($item->total_cost_price ?? 0);
        $amount = $this->sanitizeAmount($item->amount ?? 0);

        if ($costPrice < 0 || $amount < 0) {
            $this->warn("⚠️  Item ID {$item->id}: Negative values, skipping");
            return false;
        }

        return true;
    }

    /**
     * Clean and convert amount to float
     */
    private function sanitizeAmount($value): float
    {
        if (is_null($value) || $value === '') {
            return 0.0;
        }

        // Remove commas and convert to float
        $cleaned = str_replace([',' , ' '], '', (string) $value);
        return (float) $cleaned;
    }

    /**
     * Display booking information
     */
    private function displayBookingInfo(Booking $booking)
    {
        $this->info("📋 Booking Details:");
        $this->line("   ID: {$booking->id}");
        $this->line("   Grand Total: " . number_format($this->sanitizeAmount($booking->grand_total), 2));
        $this->line("   Items Count: " . $booking->items->count());
        $this->line("   Current VAT: " . ($booking->output_vat ? number_format($booking->output_vat, 2) : 'Not set'));
        $this->line("   Current Commission: " . ($booking->commission ? number_format($booking->commission, 2) : 'Not set'));
        $this->newLine();
    }

    /**
     * Show final summary
     */
    private function showSummary($processed, $failed, $skipped, $duration)
    {
        $this->newLine();
        $this->info('📊 === VAT CALCULATION SUMMARY ===');
        $this->info("✅ Successfully processed: {$processed}");

        if ($skipped > 0) {
            $this->warn("⚠️  Skipped (invalid data): {$skipped}");
        }

        if ($failed > 0) {
            $this->error("❌ Failed: {$failed}");
        }

        $this->info("⏱️  Total time: {$duration} seconds");

        if ($processed > 0) {
            $avgTime = round($duration / ($processed + $failed + $skipped), 4);
            $this->info("⚡ Average per booking: {$avgTime} seconds");
        }

        $this->newLine();

        if ($processed > 0) {
            $this->info('🎉 VAT calculation completed successfully!');
        } else {
            $this->warn('⚠️  No bookings were processed');
        }
    }
}

// ================================================================
// Additional Test Command for Verification
// ================================================================

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class TestVatCalculationResults extends Command
{
    protected $signature = 'booking:test-vat-results {--limit=10 : Number of bookings to check}';
    protected $description = 'Test and verify VAT calculation results';

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $this->info("🔍 Checking VAT calculation results for {$limit} bookings...");

        $bookings = Booking::with('items')
            ->whereNotNull('output_vat')
            ->limit($limit)
            ->get();

        if ($bookings->isEmpty()) {
            $this->warn('⚠️  No bookings found with calculated VAT');
            return;
        }

        $this->table([
            'ID', 'Grand Total', 'Calculated VAT', 'Expected VAT', 'Commission', 'Items Count', 'Status'
        ], $bookings->map(function ($booking) {
            $grandTotal = $this->sanitizeAmount($booking->grand_total);
            $expectedVat = round($grandTotal * 0.07, 2);
            $actualVat = (float) $booking->output_vat;

            $status = abs($expectedVat - $actualVat) < 0.01 ? '✅ Correct' : '❌ Wrong';

            return [
                $booking->id,
                number_format($grandTotal, 2),
                number_format($actualVat, 2),
                number_format($expectedVat, 2),
                number_format((float) $booking->commission, 2),
                $booking->items->count(),
                $status
            ];
        }));

        // Summary statistics
        $totalBookings = Booking::count();
        $calculatedBookings = Booking::whereNotNull('output_vat')->count();
        $percentage = $totalBookings > 0 ? round(($calculatedBookings / $totalBookings) * 100, 2) : 0;

        $this->newLine();
        $this->info("📊 Summary:");
        $this->info("   Total bookings: {$totalBookings}");
        $this->info("   With calculated VAT: {$calculatedBookings}");
        $this->info("   Coverage: {$percentage}%");
    }

    private function sanitizeAmount($value): float
    {
        if (is_null($value) || $value === '') {
            return 0.0;
        }

        $cleaned = str_replace([',' , ' '], '', (string) $value);
        return (float) $cleaned;
    }
}
