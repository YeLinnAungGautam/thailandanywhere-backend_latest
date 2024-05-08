<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Imports\CityImport;
use App\Models\City;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CityController extends Controller
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

        $query = City::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);

        return $this->success(CityResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'City List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $data = [
            'name' => $request->name,
        ];

        if ($file = $request->file('image')) {
            $fileData = $this->uploads($file, 'images/');
            $data['image'] = $fileData['fileName'];
        }

        $save = City::create($data);

        return $this->success(new CityResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = City::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new CityResource($find), 'City Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $find = City::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'name' => $request->name ?? $find->name
        ];

        if ($file = $request->file('image')) {
            // delete old icon
            if ($find->image) {
                Storage::delete($find->image);
            }

            $fileData = $this->uploads($file, 'images/');
            $data['image'] = $fileData['fileName'];
        }


        $find->update($data);

        return $this->success(new CityResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = City::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file' => 'required|mimes:csv,txt']);

            \Excel::import(new CityImport, $request->file('file'));

            return $this->success(null, 'CSV import is successful');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
