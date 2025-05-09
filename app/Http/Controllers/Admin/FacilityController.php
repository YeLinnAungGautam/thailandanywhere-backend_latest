<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacilityResource;
use App\Models\Facility;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FacilityController extends Controller
{
    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = Facility::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);

        return $this->success(FacilityResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Facility List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'image' => 'required'
        ]);

        if ($request->file('image')) {
            // $image = uploadFile($request->file('image'), 'images/facility/');
            $image = $this->uploads($request->file('image'), 'images/facility/');
        }

        $save = Facility::create(['name' => $request->name, 'image' => $image]);

        return $this->success(new FacilityResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = Facility::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new FacilityResource($find), 'Facility Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $find = Facility::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        if ($request->file('image')) {
            // $image = uploadFile($request->file('image'), 'images/facility/');
            $image = $this->uploads($request->file('image'), 'images/facility/');

            if ($find->image) {
                Storage::delete('images/facility/' . $find->image);
            }
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'image' => $image ?? $find->image
        ];

        $find->update($data);

        return $this->success(new FacilityResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = Facility::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        if ($find->image) {
            Storage::delete('images/facility/' . $find->image);
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }
}
