<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoFillReceiptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receipt:auto-fill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto fill missing sender and receiver data in receipt tables';

    const DEFAULT_SENDER = 'MR. THIHA @ KUMAR BHUSAL';
    const DEFAULT_RECEIVER = 'MR. THIHA @ KUMAR BHUSAL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting auto-fill process for receipt tables...');

        try {
            // Auto-fill reservation expense receipts
            $expenseUpdated = $this->autoFillExpenseReceipts();

            // Auto-fill booking receipts
            $bookingUpdated = $this->autoFillBookingReceipts();

            $this->info("✅ Auto-fill process completed successfully!");
            $this->info("📊 Summary:");
            $this->info("   - Expense receipts updated: {$expenseUpdated}");
            $this->info("   - Booking receipts updated: {$bookingUpdated}");
            $this->info("   - Total records updated: " . ($expenseUpdated + $bookingUpdated));

        } catch (\Exception $e) {
            $this->error("❌ Error during auto-fill process: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function isValidAccountName($accountName)
    {
        // Return false if:
        // - null
        // - empty string
        // - string "null" (case insensitive)
        // - only whitespace
        if (is_null($accountName)) {
            return false;
        }

        $trimmed = trim($accountName);

        if (empty($trimmed)) {
            return false;
        }

        if (strtolower($trimmed) === 'null') {
            return false;
        }

        return true;
    }

    /**
     * Auto-fill expense receipts
     */
    private function autoFillExpenseReceipts()
    {
        $this->info('🔄 Processing reservation expense receipts...');

        $updated = 0;

        // Get expense receipts that need auto-fill
        $expenseReceipts = DB::table('reservation_expense_receipts as rer')
            ->leftJoin('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->select([
                'rer.id',
                'rer.sender',
                'rer.reciever',
                'bi.product_type',
                'bi.product_id'
            ])
            ->where(function($query) {
                $query->whereNull('rer.sender')
                      ->orWhere('rer.sender', '')
                      ->orWhereNull('rer.reciever')
                      ->orWhere('rer.reciever', '');
            })
            ->get();

        $this->info("   Found {$expenseReceipts->count()} expense receipts to process");

        $progressBar = $this->output->createProgressBar($expenseReceipts->count());
        $progressBar->start();

        foreach ($expenseReceipts as $receipt) {
            $updateData = [];

            // Fill sender if empty
            if (empty($receipt->sender)) {
                $updateData['sender'] = self::DEFAULT_SENDER;
            }

            // Fill receiver if empty and account_name is valid
            if (empty($receipt->reciever)) {
                $accountName = $this->getAccountNameFromProduct($receipt->product_type, $receipt->product_id);

                if ($this->isValidAccountName($accountName)) {
                    $updateData['reciever'] = $accountName;
                }
                // If account_name is invalid, don't fill receiver (skip this record)
            }

            if (!empty($updateData)) {
                DB::table('reservation_expense_receipts')
                    ->where('id', $receipt->id)
                    ->update($updateData);
                $updated++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $updated;
    }

    private function getAccountNameFromProduct($productType, $productId)
    {
        if (empty($productType) || empty($productId)) {
            return null;
        }

        try {
            switch ($productType) {
                case 'App\\Models\\Hotel':
                    $hotel = DB::table('hotels')->where('id', $productId)->first();
                    return $hotel ? $hotel->account_name : null;

                case 'App\\Models\\EntranceTicket':
                    $ticket = DB::table('entrance_tickets')->where('id', $productId)->first();
                    return $ticket ? $ticket->account_name : null;

                default:
                    // Handle other product types if needed
                    $this->warn("Unknown product type: {$productType}");
                    return null;
            }
        } catch (\Exception $e) {
            $this->warn("Error getting account name for {$productType} ID {$productId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Auto-fill booking receipts
     */
    private function autoFillBookingReceipts()
    {
        $this->info('🔄 Processing booking receipts...');

        // Count records that need update first
        $needUpdateCount = DB::table('booking_receipts')
            ->where(function($query) {
                $query->whereNull('reciever')
                      ->orWhere('reciever', '');
            })
            ->count();

        $this->info("   Found {$needUpdateCount} booking receipts to process");

        if ($needUpdateCount > 0) {
            $progressBar = $this->output->createProgressBar($needUpdateCount);
            $progressBar->start();

            // Update booking receipts with empty receiver
            $updated = DB::table('booking_receipts')
                ->where(function($query) {
                    $query->whereNull('reciever')
                          ->orWhere('reciever', '');
                })
                ->update(['reciever' => self::DEFAULT_RECEIVER]);

            $progressBar->advance($needUpdateCount);
            $progressBar->finish();
            $this->newLine();

            return $updated;
        }

        return 0;
    }
}
