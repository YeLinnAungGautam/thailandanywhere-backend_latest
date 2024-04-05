<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductAvailableScheduleRequest;
use App\Http\Resources\ProductAvailableScheduleResource;
use App\Models\EntranceTicket;
use App\Models\Hotel;
use App\Models\ProductAvailableSchedule;
use App\Services\ProductDataService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductAvailableScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = ProductAvailableSchedule::query()
            ->with('ownerable', 'variable')
            ->when($request->product_type, function ($query) use ($request) {
                if($request->product_type === 'hotel') {
                    $query->where('ownerable_type', Hotel::class);
                } elseif($request->product_type === 'entrance_ticket') {
                    $query->where('ownerable_type', EntranceTicket::class);
                }
            })
            ->when($request->product_id, fn ($query) => $query->where('ownerable_id', $request->product_id))
            ->when($request->variation_id, fn ($query) => $query->where('ownerable_id', $request->variable_id))
            ->when($request->daterange, function ($query) use ($request) {
                $dates = explode(',', $request->daterange);

                $query->whereBetween('checkin_date', [$dates[0], $dates[1]])
                    ->orWhereBetween('checkout_date', [$dates[0], $dates[1]]);
            })
            ->when($request->date, fn ($query) => $query->whereBetween('date', $request->date))
            ->paginate(10);

        return ProductAvailableScheduleResource::collection($schedules)->additional(['result' => 1, 'message' => 'success']);
    }

    public function store(ProductAvailableScheduleRequest $request)
    {
        try {
            $insert_data = [];
            foreach($request->variations as $variation) {
                $insert_data[] = [
                    'ownerable_id' => $request->product_id,
                    'ownerable_type' => ProductDataService::getProductModelByName($request->product_type),
                    'variable_id' => $variation['variable_id'],
                    'variable_type' => ProductDataService::getVariationByProductType($request->product_type),
                    'quantity' => $variation['quantity'],
                    'checkin_date' => $variation['checkin_date'] ?? null,
                    'checkout_date' => $variation['checkout_date'] ?? null,
                    'date' => $variation['date'] ?? null,
                    'status' => $variation['status'] ?? 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('product_available_schedules')->insert($insert_data);

            return successMessage('Product available schedule is successfully created');
        } catch (Exception $e) {
            Log::error($e);

            return fail($e->getMessage());
        }
    }

    public function update(string $product_available_schedule_id, Request $request)
    {
        try {
            $schedule = ProductAvailableSchedule::find($product_available_schedule_id);

            if(is_null($schedule)) {
                throw new Exception('Product available schedule not found');
            }

            $data = [
                'quantity' => $request->quantity,
                'status' => $request->status,
            ];

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
