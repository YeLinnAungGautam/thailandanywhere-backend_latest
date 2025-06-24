<?php

namespace App\Console\Commands;

use App\Jobs\ReservationGroup\migrateBookingConfirmLetters;
use App\Jobs\ReservationGroup\migrateBookingRequestDocuments;
use App\Jobs\ReservationGroup\migrateCarInfos;
use App\Jobs\ReservationGroup\migrateCustomerPassport;
use App\Jobs\ReservationGroup\migrateExpenseMails;
use App\Jobs\ReservationGroup\migrateExpenseReceipts;
use App\Jobs\ReservationGroup\migratePaidSlips;
use App\Jobs\ReservationGroup\migrateSupplierInfos;
use App\Jobs\ReservationGroup\migrateTaxSlips;
use Illuminate\Console\Command;

class MigrateCustomerDocumentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:customer-document';

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
        migrateCustomerPassport::dispatch();

        migrateBookingRequestDocuments::dispatch();

        migrateBookingConfirmLetters::dispatch();

        migrateExpenseReceipts::dispatch();

        migrateExpenseMails::dispatch();

        migratePaidSlips::dispatch();

        migrateCarInfos::dispatch();

        migrateSupplierInfos::dispatch();

        migrateTaxSlips::dispatch();
    }
}
