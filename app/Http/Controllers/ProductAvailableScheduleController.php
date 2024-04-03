<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductAvailableScheduleRequest;
use App\Http\Resources\ProductAvailableScheduleResource;
use App\Models\ProductAvailableSchedule;
use App\Services\ProductDataService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductAvailableScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = ProductAvailableSchedule::query()
            ->with('ownerable', 'variable')
            ->paginate(10);

        return ProductAvailableScheduleResource::collection($schedules)->additional(['result' => 1, 'message' => 'success']);
    }

    public function store(ProductAvailableScheduleRequest $request)
    {
        try {
            $data = [
                'ownerable_id' => $request->product_id,
                'ownerable_type' => ProductDataService::getProductModelByName($request->product_type),
                'variable_id' => $request->variable_id,
                'variable_type' => ProductDataService::getVariationByProductType($request->product_type),
                'quantity' => $request->quantity,
            ];

            if($request->product_type === 'hotel') {
                $data['checkin_date'] = $request->checkin_date;
                $data['checkout_date'] = $request->checkout_date;
            } else {
                $data['date'] = $request->date;
            }

            $schedule = ProductAvailableSchedule::create($data);

            return success(new ProductAvailableScheduleResource($schedule));
        } catch (Exception $e) {
            Log::error($e);

            return fail($e->getMessage());
        }
    }

    public function update(string $product_available_schedule_id, ProductAvailableScheduleRequest $request)
    {
        try {
            $schedule = ProductAvailableSchedule::find($product_available_schedule_id);

            if(is_null($schedule)) {
                throw new Exception('Product available schedule not found');
            }

            $data = [
                'ownerable_id' => $request->product_id,
                'ownerable_type' => ProductDataService::getProductModelByName($request->product_type),
                'variable_id' => $request->variable_id,
                'variable_type' => ProductDataService::getVariationByProductType($request->product_type),
                'quantity' => $request->quantity,
            ];

            if($request->product_type === 'hotel') {
                $data['checkin_date'] = $request->checkin_date;
                $data['checkout_date'] = $request->checkout_date;
            } else {
                $data['date'] = $request->date;
            }

            $schedule->update($data);

            return success(new ProductAvailableScheduleResource($schedule));
        } catch (Exception $e) {
            Log::error($e);

            return fail($e->getMessage());
        }
    }

    public function destroy(string $product_available_schedule_id)
    {
        try {
            $schedule = ProductAvailableSchedule::find($product_available_schedule_id);

            if(is_null($schedule)) {
                throw new Exception('Product available schedule not found');
            }

            $schedule->delete();

            return success(null);
        } catch (Exception $e) {
            Log::error($e);

            return fail($e->getMessage());
        }
    }
}
