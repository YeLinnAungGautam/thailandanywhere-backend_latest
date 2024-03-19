<?php

namespace App\Console\Commands;

use App\Models\ReservationCarInfo;
use Illuminate\Console\Command;

class MigrateSupplierAndDriver extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:supplier-and-driver';

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
        $car_infos = ReservationCarInfo::get();

        $one_ary = ['No ONE', 'No. One', 'Number 1', 'Number One', 'Number One Taxi'];
        $victor_ary = ['Mr. Victor', 'Victor'];
        $ps_ary = ['P Sailom', 'P Silom', 'Pee silom'];
        $p_tee_ary = ['P Tee', 'P Tee - Chiang Mai', 'P Tee Chiang Mai'];
        $th_anywhere = ['TH Anywhere'];
        $th_anywhere_no_one = ['TH Anywhere & No One', 'TH Anywhere & No. One', 'TH Anywhere + No. One'];
        $supplier = ['Supplier Name', 'supplier'];

        foreach($car_infos as $car_info) {
            if($car_info->supplier_name && in_array($car_info, $one_ary)) {
                
            }
        }
    }
}
