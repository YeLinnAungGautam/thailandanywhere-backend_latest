<?php
namespace App\Services\Repository;

use App\Models\Driver;
use App\Models\DriverInfo;
use Exception;

class DriverInfoRepositoryService
{
    public function __construct(protected Driver $driver)
    {
        //
    }

    public function storeInfo(array $input)
    {
        try {
            if(!$this->driver->hasDefaultInfo()) {
                $data = ['car_number' => $input['car_number'], 'is_default' => true];
            } else {
                if($input['is_default']) {
                    $this->driver->driverInfos()->update(['is_default' => 0]);

                    $data = ['car_number' => $input['car_number'], 'is_default' => true];
                } else {
                    $data = ['car_number' => $input['car_number'], 'is_default' => false];
                }
            }

            $this->driver->driverInfos()->create($data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function updateInfo(DriverInfo $driver_info, array $input)
    {
        try {
            if($input['is_default']) {
                $this->driver->driverInfos()->update(['is_default' => 0]);

                $data = ['car_number' => $input['car_number'], 'is_default' => true];
            } else {
                $data = ['car_number' => $input['car_number'], 'is_default' => false];
            }

            $driver_info->update($data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function deleteInfo(DriverInfo $driver_info)
    {
        try {
            if($driver_info->is_default) {
                $first_info = $this->driver->driverInfos->sortBy('id')->first();
                if($first_info) {
                    $first_info->update(['is_default' => true]);
                }
            }

            $driver_info->delete();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
