<?php

namespace App\Console\Commands;

use App\Models\EntranceVariation;
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
        $records = EntranceVariation::all();

        foreach ($records as $entranceTicketVariation) {
            $name = $entranceTicketVariation->name;
            $name = explode(':', $name);
            $name = end($name);
            $name = trim($name);
            $entranceTicketVariation->name = $name;
            $entranceTicketVariation->save();
        }
    }
}
