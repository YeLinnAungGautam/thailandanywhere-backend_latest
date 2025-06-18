<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateInteractBank extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:interact-bank';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating interact_bank fields...');

        // Update booking_receipts
        $bookingUpdated = DB::table('booking_receipts')->update(['interact_bank' => 'personal']);
        $this->info("Updated {$bookingUpdated} booking_receipts records");

        // Update reservation_expense_receipts
        $expenseUpdated = DB::table('reservation_expense_receipts')->update(['interact_bank' => 'personal']);
        $this->info("Updated {$expenseUpdated} reservation_expense_receipts records");

        $this->info('All interact_bank fields updated to "personal" successfully!');
    }
}
