<?php

namespace App\Jobs;

use App\Mail\SendSaleReportMail;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReservationReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $type;

    /**
     * Create a new job instance.
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $date = Carbon::yesterday()->format('Y-m-d');

        if($this->type == 'daily') {
            $daterange = $date . "," . $date;
        } elseif ($this->type == 'weekly') {
            $start_week = Carbon::yesterday()->startOfWeek()->format('Y-m-d');
            $end_week = Carbon::yesterday()->endOfWeek()->format('Y-m-d');

            $daterange = $start_week . ',' . $end_week;
        }

        Mail::to('ceo@thanywhere.com')->send(new SendSaleReportMail($daterange, $this->type));
    }
}
