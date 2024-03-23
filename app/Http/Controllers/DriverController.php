<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $drivers = Driver::with('supplier', 'driverInfos')
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            })
            ->when($request->supplier_id, fn ($query) => $query->where('supplier_id', $request->supplier_id))
            ->paginate($request->limit ?? 20);

        return $this->success(DriverResource::collection($drivers)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($drivers->total() / $drivers->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Driver List');
    }

    public function store(DriverRequest $request)
    {
        try {
            $input = $request->validated();

            if ($request->file('profile')) {
                $input['profile'] = uploadFile($request->file('profile'), 'images/driver/');
            }

            if ($request->file('car_photo')) {
                $input['car_photo'] = uploadFile($request->file('car_photo'), 'images/driver/');
            }

            $driver = Driver::create($input);

            return $this->success(new DriverResource($driver), 'Successfully created', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $driver = Driver::with('supplier', 'driverInfos')->find($id);

        if(is_null($driver)) {
            return $this->error(null, 'Driver not found.');
        }

        return $this->success(new DriverResource($driver), 'Driver Detail', 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DriverRequest $request, string $id)
    {
        try {
            $driver = Driver::find($id);

            if(is_null($driver)) {
                throw new Exception('Driver not found');
            }

            $input = $request->validated();

            if ($request->file('profile')) {
                $input['profile'] = uploadFile($request->file('profile'), 'images/driver/');
            }

            if ($request->file('car_photo')) {
                $input['car_photo'] = uploadFile($request->file('car_photo'), 'images/driver/');
            }

            $driver->update($input);

            return $this->success(new DriverResource($driver), 'Successfully updated', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $driver = Driver::find($id);

            if(is_null($driver)) {
                throw new Exception('Driver not found');
            }

            $driver->delete();

            return $this->success(null, 'Successfully deleted', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }
}
