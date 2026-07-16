<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVanTourRequest;
use App\Http\Requests\UpdateVanTourRequest;
use App\Http\Resources\VanTourV2Resource;
use App\Models\City;
use App\Models\Destination;
use App\Models\VanTour;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class VanTourController extends Controller
{
    use HttpResponses;

    /**
     * Relations always eager-loaded for the resource output.
     */
    private const WITH = ['cities', 'routePlans', 'cars'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = VanTour::query()
            ->with(self::WITH)
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            })
            ->when($request->query('type'), function ($q) use ($request) {
                $q->where('type', $request->query('type'));
            })
            ->when($request->query('city_id'), function ($q) use ($request) {
                $q->whereHas('cities', fn ($c) => $c->where('cities.id', $request->query('city_id')));
            })
            ->when($request->query('route_plan_id'), function ($q) use ($request) {
                $q->whereHas('routePlans', fn ($r) => $r->where('route_plans.id', $request->query('route_plan_id')));
            })
            ->when($request->query('car_id'), function ($q) use ($request) {
                $q->whereHas('cars', fn ($c) => $c->where('cars.id', $request->query('car_id')));
            })
            ->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(VanTourV2Resource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Van Tour List');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVanTourRequest $request)
    {
        $save = VanTour::create([
            'name' => $request->name,
            'sku_code' => $request->sku_code,
            'type' => $request->type ?? VanTour::TYPES['car_rental'],
            'supplier_cost' => $request->supplier_cost,
        ]);

        $this->syncRelations($save, $request);

        return $this->success(new VanTourV2Resource($save->load(self::WITH)), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = VanTour::with(self::WITH)->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $payload = (new VanTourV2Resource($find))->resolve(request());

        $payload['route_plans'] = $find->routePlans()
            ->get()
            ->map(function ($rp) {
                return [
                    'route_id'        => $rp->id,
                    'route'           => $rp->route,
                    'city_ids'        => $rp->city_ids ?? [],
                    'destination_ids' => $rp->destination_ids ?? [],
                    'cities'          => City::whereIn('id', $rp->city_ids ?? [])->get(['id', 'name']),
                    'destinations'    => Destination::whereIn('id', $rp->destination_ids ?? [])->get(['id', 'name']),
                ];
            })
            ->values();

        return $this->success($payload, 'Van Tour Detail');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVanTourRequest $request, string $id)
    {
        $find = VanTour::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->update([
            'name' => $request->name ?? $find->name,
            'sku_code' => $request->sku_code ?? $find->sku_code,
            'type' => $request->type ?? $find->type,
            'supplier_cost' => $request->has('supplier_cost') ? $request->supplier_cost : $find->supplier_cost,
        ]);

        $this->syncRelations($find, $request);

        return $this->success(new VanTourV2Resource($find->load(self::WITH)), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(string $id)
    {
        $find = VanTour::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    /**
     * Permanently delete the resource and detach all its relations.
     */
    public function hardDelete(string $id)
    {
        $find = VanTour::onlyTrashed()->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->cities()->detach();
        $find->routePlans()->detach();
        $find->cars()->detach();

        $find->forceDelete();

        return $this->success(null, 'Van Tour is completely deleted');
    }

    /**
     * Restore a soft-deleted resource.
     */
    public function restore(string $id)
    {
        $find = VanTour::onlyTrashed()->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->restore();

        return $this->success(null, 'Van Tour is successfully restored');
    }

    /**
     * Sync the many-to-many relations shared by store() and update().
     */
    private function syncRelations(VanTour $vantour, Request $request): void
    {
        if ($request->filled('city_ids')) {
            $vantour->cities()->sync($request->city_ids);
        }

        if ($request->filled('route_plan_ids')) {
            $vantour->routePlans()->sync($request->route_plan_ids);
        }

        if ($request->filled('car_ids')) {
            $prices = $request->prices ?? [];
            $agentPrices = $request->agent_prices ?? [];
            $costs = $request->costs ?? [];

            $carData = array_combine(
                $request->car_ids,
                array_map(function ($price, $agentPrice, $cost) {
                    return [
                        'price' => $price,
                        'agent_price' => $agentPrice,
                        'cost' => $cost,
                    ];
                }, $prices, $agentPrices, $costs)
            );

            $vantour->cars()->sync($carData);
        }
    }
}
