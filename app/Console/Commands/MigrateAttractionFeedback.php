<?php

namespace App\Console\Commands;

use App\Models\EntranceTicket;
use App\Models\EntranceTicketVariation;
use Illuminate\Console\Command;

class MigrateAttractionFeedback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:feedback';

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
        // Nong Nooch Pattaya: A4 Adult Ticket(Admission Fee +Sighting-seeing Bus + Show)
        // remove text in front of : in above text

        EntranceTicketVariation::chunk(100, function ($entranceTicketVariations) {
            foreach ($entranceTicketVariations as $entranceTicketVariation) {
                $name = $entranceTicketVariation->name;
                $name = explode(':', $name);
                $name = end($name);
                $name = trim($name);
                $entranceTicketVariation->name = $name;
                $entranceTicketVariation->save();
            }
        });
    }
}
