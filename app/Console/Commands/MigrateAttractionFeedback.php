<?php

namespace App\Console\Commands;

use App\Models\EntranceTicket;
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
        $records = EntranceTicket::all();

        foreach ($records as $entranceTicket) {
            $meta_data = ['is_show' => 1];

            $entranceTicket->update([
                'meta_data' => json_encode($meta_data),
            ]);
        }
    }
}
