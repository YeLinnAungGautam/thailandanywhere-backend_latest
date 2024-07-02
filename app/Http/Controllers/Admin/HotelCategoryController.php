<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelCategoryResource;
use App\Models\HotelCategory;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class HotelCategoryController extends Controller
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = HotelCategory::query()
            ->when($request->search, fn ($query) => $query->where('name', 'LIKE', "%{$request->search}%"))
            ->paginate($request->limit ?? 20);

        return $this->success(HotelCategoryResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Hotel Category List');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);

        $category = HotelCategory::create(['name' => $request->name]);

        return $this->success(new HotelCategoryResource($category), 'Successfully created');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate(['name' => 'required']);

        $hotel_category = HotelCategory::findOrFail($id);

        $hotel_category->update(['name' => $request->name]);

        return $this->success(new HotelCategoryResource($hotel_category), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $hotel_category = HotelCategory::findOrFail($id);

        $hotel_category->delete();

        return $this->success(null, 'Successfully deleted');
    }
}
