<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoutePlanRequest;
use App\Http\Requests\UpdateRoutePlanRequest;
use App\Http\Resources\PrivateVanTourResource;
use App\Http\Resources\RoutePlanResource;
use App\Models\RoutePlan;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoutePlanController extends Controller
{
    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of route plans.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = RoutePlan::query()->with('privateVanTours')
            ->when($search, function ($q) use ($search) {
                $q->where('english_description', 'LIKE', "%{$search}%")
                  ->orWhere('mm_description', 'LIKE', "%{$search}%");
            })
            ->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(RoutePlanResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Route Plan List');
    }

    public function store(StoreRoutePlanRequest $request)
    {
        $data = [
            'destination_ids'     => $request->destination_ids ?? [],
            'city_ids'            => $request->city_ids ?? [],
            'english_description' => $request->english_description,
            'mm_description'      => $request->mm_description,
            'route'               => $request->route,
        ];

        if ($file = $request->file('main_cover_photo')) {
            $fileData = $this->uploads($file, 'images/');
            $data['main_cover_photo'] = $fileData['fileName'];
        }

        if ($request->file('other_photos')) {
            $photos = [];
            foreach ($request->file('other_photos') as $photo) {
                $fileData = $this->uploads($photo, 'images/');
                $photos[] = $fileData['fileName'];
            }
            $data['other_photos'] = $photos;
        }

        $save = RoutePlan::create($data);

        // Sync attached packages
        if ($request->vantour_ids) {
            $save->privateVanTours()->sync($request->vantour_ids);
        }

        return $this->success(new RoutePlanResource($save), 'Successfully created');
    }

    public function update(UpdateRoutePlanRequest $request, string $id)
    {
        $find = RoutePlan::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'destination_ids'     => $request->destination_ids ?? $find->destination_ids,
            'city_ids'            => $request->city_ids ?? $find->city_ids,
            'english_description' => $request->english_description ?? $find->english_description,
            'mm_description'      => $request->mm_description ?? $find->mm_description,
            'route'               => $request->route ?? $find->route,
        ];

        if ($file = $request->file('main_cover_photo')) {
            $fileData = $this->uploads($file, 'images/');
            $data['main_cover_photo'] = $fileData['fileName'];

            if ($find->main_cover_photo) {
                Storage::delete('images/' . $find->main_cover_photo);
            }
        }

        if ($request->file('other_photos')) {
            foreach ($find->other_photos ?? [] as $existingPhoto) {
                Storage::delete('images/' . $existingPhoto);
            }
            $photos = [];
            foreach ($request->file('other_photos') as $photo) {
                $fileData = $this->uploads($photo, 'images/');
                $photos[] = $fileData['fileName'];
            }
            $data['other_photos'] = $photos;
        }

        $find->update($data);

        // Sync packages if provided
        if ($request->has('vantour_ids')) {
            $find->privateVanTours()->sync($request->vantour_ids);
        }

        return $this->success(new RoutePlanResource($find), 'Successfully updated');
    }

    public function destroy(string $id)
    {
        $find = RoutePlan::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        // Detach all packages first
        $find->privateVanTours()->detach();
        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function show(string $id)
    {
        $find = RoutePlan::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $payload = (new RoutePlanResource($find))->resolve(request());
        $payload['van_tours'] = PrivateVanTourResource::collection($find->privateVanTours()->get());
        $payload['destinations'] = $find->destinations()->get();
        $payload['cities'] = $find->cities()->get();

        return $this->success($payload, 'Route Plan Detail');
    }
}
