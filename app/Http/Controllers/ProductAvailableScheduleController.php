<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductAvailableScheduleRequest;
use App\Http\Resources\ProductAvailableScheduleResource;
use App\Models\Admin;
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
            ->with('ownerable', 'variable', 'createdBy' , 'updatedBy')
            ->when($request->product_type, function ($query) use ($request) {
                if($request->product_type === 'hotel') {
                    $query->where('ownerable_type', Hotel::class);
                } elseif($request->product_type === 'entrance_ticket') {
                    $query->where('ownerable_type', EntranceTicket::class);
                }
            })
            ->when($request->product_id, fn ($query) => $query->where('ownerable_id', $request->product_id))
            ->when($request->variation_id, fn ($query) => $query->where('ownerable_id', $request->variable_id))
            ->when($request->status, fn ($query) => $query->where('status', $request->status))
            ->when($request->created_by, fn ($query) => $query->where('created_by', $request->created_by))
            ->when($request->daterange, function ($query) use ($request) {
                $dates = explode(',', $request->daterange);

                $query->whereBetween('checkin_date', [$dates[0], $dates[1]])
                    ->orWhereBetween('checkout_date', [$dates[0], $dates[1]]);
            })
            ->when($request->date, fn ($query) => $query->where('date', $request->date))
            ->orderBy('created_at', $request->order_by ?? 'asc')
            ->paginate(10);

        return ProductAvailableScheduleResource::collection($schedules)->additional(['result' => 1, 'message' => 'success']);
    }

    public function creatorRankings(Request $request)
    {
        try {
            $request->validate([
                'period_type' => 'required|in:day,month',
                'date' => 'required|date',
            ]);

            $query = ProductAvailableSchedule::query()
                ->select(
                    'created_by',
                    'ownerable_type',
                    DB::raw('COUNT(*) as count')
                )
                ->whereNotNull('created_by');

            if ($request->period_type === 'day') {
                $query->whereDate('created_at', $request->date);
            } elseif ($request->period_type === 'month') {
                $date = \Carbon\Carbon::parse($request->date);
                $query->whereYear('created_at', $date->year)
                      ->whereMonth('created_at', $date->month);
            }

            // Optional filter by specific product type
            if ($request->has('product_type')) {
                if ($request->product_type === 'hotel') {
                    $query->where('ownerable_type', Hotel::class);
                } elseif ($request->product_type === 'entrance_ticket') {
                    $query->where('ownerable_type', EntranceTicket::class);
                }
            }

            $results = $query->groupBy('created_by', 'ownerable_type')
                ->orderBy('created_by')
                ->get();

            // Group by user and calculate totals
            $userStats = [];

            foreach ($results as $result) {
                $userId = $result->created_by;

                if (!isset($userStats[$userId])) {
                    $userStats[$userId] = [
                        'user_id' => $userId,
                        'user' => null,
                        'hotel_count' => 0,
                        'entrance_ticket_count' => 0,
                        'other_count' => 0,
                        'total_count' => 0,
                    ];
                }

                // Categorize by product type
                if ($result->ownerable_type === 'App\\Models\\Hotel') {
                    $userStats[$userId]['hotel_count'] = $result->count;
                } elseif ($result->ownerable_type === 'App\\Models\\EntranceTicket') {
                    $userStats[$userId]['entrance_ticket_count'] = $result->count;
                } else {
                    $userStats[$userId]['other_count'] += $result->count;
                }

                $userStats[$userId]['total_count'] += $result->count;
            }

            // Load user information
            $userIds = array_keys($userStats);
            $users = Admin::whereIn('id', $userIds)->get()->keyBy('id');

            foreach ($userStats as $userId => &$stats) {
                if (isset($users[$userId])) {
                    $stats['user'] = [
                        'name' => $users[$userId]->name,
                        'email' => $users[$userId]->email,
                    ];
                }
            }

            // Sort by total count and add rank
            $rankings = collect($userStats)
                ->sortByDesc('total_count')
                ->values()
                ->map(function ($item, $index) {
                    return array_merge(['rank' => $index + 1], $item);
                });

            return response()->json([
                'result' => 1,
                'message' => 'success',
                'period_type' => $request->period_type,
                'date' => $request->date,
                'data' => $rankings
            ]);

        } catch (Exception $e) {
            Log::error($e);
            return fail($e->getMessage());
        }
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
                    'child_qty' => $variation['child_qty'] ?? 0,
                    'customer_name' => $variation['customer_name'] ?? null,
                    'customer_phnumber' => $variation['customer_phnumber'] ?? null,
                    'checkin_date' => $variation['checkin_date'] ?? null,
                    'checkout_date' => $variation['checkout_date'] ?? null,
                    'date' => $variation['date'] ?? null,
                    'created_by' => auth()->id(),
                    'commands' => $variation['commands'] ?? null,
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
                'res_comment' => $request->res_comment,
                'updated_by' => auth()->id(),
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

    public function changeStatus(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|string',
            ]);

            // Convert comma-separated string to array
            $ids = explode(',', $request->ids);

            // Remove any empty values and trim whitespace
            $ids = array_filter(array_map('trim', $ids));

            if (empty($ids)) {
                throw new Exception('No valid IDs provided');
            }

            // Update all schedules with the given IDs
            $updated = ProductAvailableSchedule::whereIn('id', $ids)
                ->update([
                    'finish_booking' => 1,
                ]);

            if ($updated === 0) {
                throw new Exception('No schedules found with the provided IDs');
            }

            // Get the updated schedules to return
            $schedules = ProductAvailableSchedule::whereIn('id', $ids)->get();

            return success(
                ProductAvailableScheduleResource::collection($schedules),
                "{$updated} schedule(s) updated successfully"
            );

        } catch (Exception $e) {
            Log::error($e);
            return fail($e->getMessage());
        }
    }
}
