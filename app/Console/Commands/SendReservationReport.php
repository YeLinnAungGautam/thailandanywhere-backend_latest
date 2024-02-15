<?php

namespace App\Console\Commands;

use App\Jobs\SendReservationReportJob;
use Illuminate\Console\Command;

class SendReservationReport extends Command
{
    protected $type;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:reservation-report {type}';

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
        SendReservationReportJob::dispatch($this->argument('type'));
    }
}
