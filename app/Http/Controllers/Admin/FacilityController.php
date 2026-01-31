<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacilityResource;
use App\Models\Facility;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
        $active_only = $request->query('active_only', false);

        $query = Facility::query();

        if ($active_only) {
            $query->active();
        }

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
     * ✅ Get simple list (no pagination) - for dropdowns
     */
    public function simpleList()
    {
        $facilities = Facility::active()->get();
        return $this->success([
            'data' => FacilityResource::collection($facilities)
        ], 'Facility List');
    }

    /**
     * ✅ AI Generate: Get or Create facilities (check duplicates)
     */
    public function getOrCreateBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'facilities' => 'required|array|min:1',
            'facilities.*.name' => 'required|string|max:255',
            'facilities.*.icon' => 'nullable|string|max:100',
            'facilities.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed', 422);
        }

        $results = [
            'created' => [],
            'existing' => [],
        ];

        foreach ($request->facilities as $facilityData) {
            $existing = Facility::whereRaw('LOWER(name) = ?', [strtolower(trim($facilityData['name']))])->first();

            if ($existing) {
                $results['existing'][] = $existing;
            } else {
                $facility = Facility::create([
                    'name' => trim($facilityData['name']),
                    'icon' => $facilityData['icon'] ?? null,
                    'is_active' => $facilityData['is_active'] ?? true,
                    'image' => null,
                ]);

                $results['created'][] = $facility;
            }
        }

        // ✅ Return structure that matches frontend expectations
        return $this->success([
            'facilities' => FacilityResource::collection(
                collect($results['created'])->merge($results['existing'])
            ),
            'stats' => [
                'total' => count($results['created']) + count($results['existing']),
                'created' => count($results['created']),
                'existing' => count($results['existing']),
            ]
        ], count($results['created']) . ' created, ' . count($results['existing']) . ' already existed');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        // ✅ Check duplicate (case-insensitive)
        $existing = Facility::whereRaw('LOWER(name) = ?', [strtolower(trim($request->name))])->first();

        if ($existing) {
            return $this->error(null, 'Facility with this name already exists', 422);
        }

        $image = null;
        if ($request->file('image')) {
            $image = uploadFile($request->file('image'), 'images/facility/');
        }

        $save = Facility::create([
            'name' => trim($request->name),
            'image' => $image,
            'icon' => $request->icon,
            'is_active' => $request->is_active ?? true,
        ]);

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

        $request->validate([
            'name' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        // ✅ Check duplicate name (excluding current record)
        if ($request->name) {
            $existing = Facility::whereRaw('LOWER(name) = ?', [strtolower(trim($request->name))])
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return $this->error(null, 'Facility with this name already exists', 422);
            }
        }

        $image = $find->image;

        if ($request->file('image')) {
            $image = uploadFile($request->file('image'), 'images/facility/');

            if ($find->image) {
                Storage::delete('images/facility/' . $find->image);
            }
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'image' => $image,
            'icon' => $request->icon ?? $find->icon,
            'is_active' => $request->is_active ?? $find->is_active,
        ];

        $find->update($data);

        return $this->success(new FacilityResource($find), 'Successfully updated');
    }

    /**
     * ✅ Toggle facility status (activate/deactivate)
     */
    public function toggleStatus(string $id)
    {
        $facility = Facility::find($id);
        if (!$facility) {
            return $this->error(null, 'Data not found', 404);
        }

        $facility->update(['is_active' => !$facility->is_active]);

        return $this->success(
            new FacilityResource($facility),
            'Status updated successfully'
        );
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

        // ✅ Detach from all hotels before deleting
        $find->hotels()->detach();

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }
}
