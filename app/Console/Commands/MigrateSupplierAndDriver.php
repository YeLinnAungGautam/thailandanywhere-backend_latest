<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Models\ReservationCarInfo;
use App\Models\Supplier;
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
        $car_infos = ReservationCarInfo::all();

        $one_ary = ['No ONE', 'No. One', 'Number 1', 'Number One', 'Number One Taxi'];
        $victor_ary = ['Mr. Victor', 'Victor'];
        $ps_ary = ['P Sailom', 'P Silom', 'Pee silom'];
        $p_tee_ary = ['P Tee', 'P Tee - Chiang Mai', 'P Tee Chiang Mai'];
        $th_anywhere = ['TH Anywhere'];
        $th_anywhere_no_one = ['TH Anywhere & No One', 'TH Anywhere & No. One', 'TH Anywhere + No. One'];
        $supplier = ['Supplier Name', 'supplier'];

        foreach($car_infos as $car_info) {
            if($car_info->supplier_name) {
                if(in_array($car_info->supplier_name, $one_ary)) {
                    $this->updateInfos($car_info, 'Number One');
                }

                if(in_array($car_info->supplier_name, $victor_ary)) {
                    $this->updateInfos($car_info, 'Victor Car Service');
                }

                if(in_array($car_info->supplier_name, $ps_ary)) {
                    $this->updateInfos($car_info, 'Pee silom');
                }

                if(in_array($car_info->supplier_name, $p_tee_ary)) {
                    $this->updateInfos($car_info, 'P Tee');
                }

                if(in_array($car_info->supplier_name, $th_anywhere)) {
                    $this->updateInfos($car_info, 'TH Anywhere');
                }

                if(in_array($car_info->supplier_name, $th_anywhere_no_one)) {
                    $this->updateInfos($car_info, 'TH Anywhere & No One');
                }

                if(in_array($car_info->supplier_name, $supplier)) {
                    $this->updateInfos($car_info, 'Supplier');
                }
            }
        }

        $this->info('Successfully migrated');
    }

    private function updateInfos($car_info, $supplier_name)
    {
        $supplier = Supplier::firstOrCreate(
            ['name' => $supplier_name],
            [
                'contact' => 'example contact',
                'logo' => 'example.png'
            ],
        );

        $driver = Driver::firstOrCreate(
            [
                'supplier_id' => $supplier->id,
                'name' => $car_info->driver_name ?? 'example name',
                'contact' => $car_info->driver_contact ?? 'example contact'
            ],
            [
                'profile' => $car_info->driver_contact ?? 'example.png',
                'car_photo' => $car_info->car_photo ?? 'example.png'
            ]
        );

        $car_info->update([
            'supplier_id' => $supplier->id,
            'driver_id' => $driver->id
        ]);
    }
}
