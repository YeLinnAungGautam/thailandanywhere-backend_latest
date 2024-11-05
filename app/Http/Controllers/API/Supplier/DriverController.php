<?php

namespace App\Http\Controllers\API\Supplier;

use Exception;
use App\Models\Driver;
use App\Models\BookingItem;
use Illuminate\Http\Request;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriverRequest;
use App\Http\Resources\DriverResource;
use App\Services\Repository\CarBookingRepositoryService;

class DriverController extends Controller
{
    use HttpResponses;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $drivers = Driver::with('supplier', 'driverInfos')
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            })
            ->when($user->id, fn ($query) => $query->where('supplier_id', $user->id))
            ->paginate($request->limit ?? 20);

        return $this->success(DriverResource::collection($drivers)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($drivers->total() / $drivers->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Driver List');
        // return response()->json(['message' => $user]);
    }

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $driver = Driver::with('supplier', 'driverInfos')->find($id);

        if (is_null($driver)) {
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

            if (is_null($driver)) {
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $driver = Driver::find($id);

            if (is_null($driver)) {
                throw new Exception('Driver not found');
            }

            $driver->delete();

            return $this->success(null, 'Successfully deleted', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    public function assign(string $driver_id, string $booking_item_id)
    {
        try {
            $driver = Driver::find($driver_id);

            if (is_null($driver)) {
                throw new Exception('Driver not found');
            }

            $booking_item = BookingItem::find($booking_item_id);

            if (is_null($booking_item)) {
                throw new Exception('Reservation not found');
            }

            $data = CarBookingRepositoryService::updateBooking($booking_item, $request);

            return $this->success(null, 'Successfully assigned', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }
}
