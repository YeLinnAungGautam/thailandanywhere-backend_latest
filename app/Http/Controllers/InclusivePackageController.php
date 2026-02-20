<?php

namespace App\Http\Controllers;

use App\Models\InclusivePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InclusivePackageController extends Controller
{
    // PackageController.php

    public function index(Request $request)
    {
        $query = InclusivePackage::query();

        if ($request->filled('search'))
            $query->where('package_name', 'like', '%'.$request->search.'%');
        if ($request->filled('status'))
            $query->where('status', $request->status);
        if ($request->filled('start_date'))
            $query->whereDate('start_date', '>=', $request->start_date);
        if ($request->filled('end_date'))
            $query->whereDate('start_date', '<=', $request->end_date);

        $packages = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $packages->items(),
            'meta'    => [
                'current_page' => $packages->currentPage(),
                'last_page'    => $packages->lastPage(),
                'per_page'     => $packages->perPage(),
                'total'        => $packages->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $startDate = Carbon::parse($request->start_date);

        $package = InclusivePackage::create([
            'package_name'        => $request->package_name ?? 'Tour Package',
            'adults'              => $request->adults,
            'children'            => $request->children,
            'start_date'          => $startDate,
            'end_date'            => $startDate->copy()->addDays($request->nights),
            'nights'              => $request->nights,
            'total_days'          => $request->nights + 1,
            'day_city_map'        => $request->day_city_map ?? [],
            'attractions'         => $request->attractions ?? [],
            'hotels'              => $request->hotels ?? [],
            'van_tours'           => $request->van_tours ?? [],
            'ordered_items'       => $request->ordered_items ?? [],
            'descriptions'        => $request->descriptions ?? [],
            'total_cost_price'    => $request->total_cost_price ?? 0,
            'total_selling_price' => $request->total_selling_price ?? 0,
            'status'              => 'draft',
            'created_by'          => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $package], 201);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data'    => InclusivePackage::findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $package   = InclusivePackage::findOrFail($id);
        $startDate = Carbon::parse($request->start_date);

        $package->update([
            'package_name'        => $request->package_name,
            'adults'              => $request->adults,
            'children'            => $request->children,
            'start_date'          => $startDate,
            'end_date'            => $startDate->copy()->addDays($request->nights),
            'nights'              => $request->nights,
            'total_days'          => $request->nights + 1,
            'day_city_map'        => $request->day_city_map ?? [],
            'attractions'         => $request->attractions ?? [],
            'hotels'              => $request->hotels ?? [],
            'van_tours'           => $request->van_tours ?? [],
            'ordered_items'       => $request->ordered_items ?? [],
            'descriptions'        => $request->descriptions ?? [],
            'total_cost_price'    => $request->total_cost_price ?? 0,
            'total_selling_price' => $request->total_selling_price ?? 0,
        ]);

        return response()->json(['success' => true, 'data' => $package]);
    }

    public function destroy($id)
    {
        InclusivePackage::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }
}
