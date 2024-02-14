<?php

namespace App\Console\Commands;

use App\Jobs\SendReservationReportJob;
use Illuminate\Console\Command;

class SendWeeklyReservationReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:weekly-reservation-report';

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
        SendReservationReportJob::dispatch('weekly');
    }
}
