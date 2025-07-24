<?php

namespace App\Http\Controllers;

use App\Http\Resources\RofacilityResource;
use App\Models\Rofacility;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RofacilityController extends Controller
{
    use HttpResponses;
    use ImageManager;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $limit = $request->query('limit', 10);
        $rofacilities = Rofacility::query();
        if ($search) {
            $rofacilities->where('name', 'like', '%' . $search . '%');
        }
        $rofacilities = $rofacilities->paginate($limit);

        return $this->success(
            RofacilityResource::collection($rofacilities)->additional([
                'meta' => [
                    'total_page' => (int) ceil($rofacilities->total() / $rofacilities->perPage()),
                ],
            ])
            ->response()
            ->getData(),
            'Rofacilities retrieved successfully.'
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $data = [
            'name' => $request->name,
        ];

        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $fileData = $this->uploads($file, 'icons/');
            $data['icon'] = $fileData['fileName'];
        }

        $rofacility = Rofacility::create($data);

        return $this->success(new RofacilityResource($rofacility), 'Rofacility created successfully.');
    }

    public function update(Request $request, $id)
    {
        $rofacility = Rofacility::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $data = [
            'name' => $request->name,
        ];

        if ($request->hasFile('icon')) {
            // Delete the old icon if it exists
            if ($rofacility->icon) {
                Storage::delete('icons/' . $rofacility->icon);
            }
            // Upload the new icon
            $file = $request->file('icon');
            $fileData = $this->uploads($file, 'icons/');
            $data['icon'] = $fileData['fileName'];
        }

        $rofacility->update($data);

        return $this->success(new RofacilityResource($rofacility), 'Rofacility updated successfully.');
    }

    public function destroy($id)
    {
        $rofacility = Rofacility::findOrFail($id);

        // Delete the icon if it exists
        if ($rofacility->icon) {
            Storage::delete('icons/' . $rofacility->icon);
        }

        $rofacility->delete();

        return $this->success(null, 'Rofacility deleted successfully.');
    }
}
