<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverInfoRequest;
use App\Http\Resources\DriverInfoResource;
use App\Models\Driver;
use App\Models\DriverInfo;
use App\Services\Repository\DriverInfoRepositoryService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Support\Facades\Log;

class DriverInfoController extends Controller
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(string $driver_id)
    {
        try {
            if(!$driver = Driver::find($driver_id)) {
                throw new Exception('Driver not found.');
            }

            $driver_infos = $driver->driverInfos()->orderByDESC('is_default')->get();

            return DriverInfoResource::collection($driver_infos)->additional(['result' => 1, 'message' => 'success']);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(string $driver_id, DriverInfoRequest $request)
    {
        try {
            if(!$driver = Driver::find($driver_id)) {
                throw new Exception('Driver not found.');
            }

            (new DriverInfoRepositoryService($driver))->storeInfo($request->validated());

            return $this->success(null, 'Driver info is successfully created');
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(string $driver_id, DriverInfoRequest $request, string $driver_info_id)
    {
        try {
            if(!$driver = Driver::find($driver_id)) {
                throw new Exception('Driver not found.');
            }

            if(!$driver_info = DriverInfo::find($driver_info_id)) {
                throw new Exception('Driver information not found.');
            }

            (new DriverInfoRepositoryService($driver))->updateInfo($driver_info, $request->validated());

            return $this->success(null, 'Driver info is successfully updated');
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $driver_id, string $driver_info_id)
    {
        try {
            if(!$driver = Driver::find($driver_id)) {
                throw new Exception('Driver not found.');
            }

            if(!$driver_info = DriverInfo::find($driver_info_id)) {
                throw new Exception('Driver information not found.');
            }

            (new DriverInfoRepositoryService($driver))->deleteInfo($driver_info);

            return $this->success(null, 'Driver info is successfully deleted');
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }
}
