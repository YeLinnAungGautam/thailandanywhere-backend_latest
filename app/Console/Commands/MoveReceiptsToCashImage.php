<?php

namespace App\Console\Commands;

use App\Jobs\MoveAllReceiptDataToCashImagesJob;
use Illuminate\Console\Command;

class MoveReceiptsToCashImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receipts:move-to-cash-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move all receipt data from both tables to cash_images table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Moving all receipt data to cash_images table...');

        if (!$this->confirm('This will copy all data from booking_receipts and reservation_expense_receipt to cash_images table. Continue?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        try {
            // Run the job
            (new MoveAllReceiptDataToCashImagesJob)->handle();

            $this->info('✅ Successfully moved all receipt data!');
            $this->line('');
            $this->info('📋 What was moved:');
            $this->info('• booking_receipts → cash_images (relatable_type: App\\Models\\Booking)');
            $this->info('• reservation_expense_receipt → cash_images (relatable_type: App\\Models\\BookingItemGroup)');

        } catch (\Exception $e) {
            $this->error('❌ Failed to move data: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
