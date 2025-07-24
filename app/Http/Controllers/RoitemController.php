<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoitemResource;
use App\Models\Roitem;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoitemController extends Controller
{
    use HttpResponses;
    use ImageManager;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $limit = $request->query('limit', 10);
        $rofacilityId = $request->query('rofacility_id');
        $roitems = Roitem::query();

        if ($search) {
            $roitems->where('name', 'like', '%' . $search . '%');
        }
        if ($rofacilityId) {
            $roitems->where('rofacility_id', $rofacilityId);
        }
        $roitems->with('rofacility'); // Assuming Roitem has a relationship with Rofacility

        $roitems = $roitems->paginate($limit);

        return $this->success(
            RoitemResource::collection($roitems)->additional([
                'meta' => [
                    'total_page' => (int) ceil($roitems->total() / $roitems->perPage()),
                ],
            ])
            ->response()
            ->getData(),
            'Roitems retrieved successfully.'
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'rofacility_id' => 'required|exists:rofacilities,id',
        ]);

        $data = [
            'name' => $request->name,
            'rofacility_id' => $request->rofacility_id,
        ];

        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $fileData = $this->uploads($file, 'icons/');
            $data['icon'] = $fileData['fileName'];
        }

        $roitem = Roitem::create($data);

        return $this->success(new RoitemResource($roitem), 'Roitem created successfully.');
    }

    public function update(Request $request, $id)
    {
        $roitem = Roitem::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'rofacility_id' => 'required|exists:rofacilities,id',
        ]);

        $data = [
            'name' => $request->name,
            'rofacility_id' => $request->rofacility_id,
        ];

        if ($request->hasFile('icon')) {

            // Delete the old icon if it exists
            if ($roitem->icon) {
                Storage::delete('icons/' . $roitem->icon);
            }
            // Upload the new icon
            $file = $request->file('icon');
            $fileData = $this->uploads($file, 'icons/');
            $data['icon'] = $fileData['fileName'];
        }

        $roitem->update($data);

        return $this->success(new RoitemResource($roitem), 'Roitem updated successfully.');
    }

    public function destroy($id)
    {
        $roitem = Roitem::findOrFail($id);

        // Delete the icon if it exists
        if ($roitem->icon) {
            Storage::delete('icons/' . $roitem->icon);
        }

        $roitem->delete();

        return $this->success(null, 'Roitem deleted successfully.');
    }
}
