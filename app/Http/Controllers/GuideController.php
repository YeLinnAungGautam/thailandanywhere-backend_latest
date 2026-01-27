<?php

namespace App\Http\Controllers;

use App\Http\Resources\GuideResource;
use App\Models\Guide;
use App\Models\Area;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuideController extends Controller
{
    use HttpResponses, ImageManager;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $guides = Guide::with('cities')->latest()->paginate(15);
        $query = Guide::with('cities')->when(request('search'), function ($query, $search) { $query->where('name', 'like', "%{$search}%"); })->latest();

        $guides = $query->paginate(10);
        return $this->success(GuideResource::collection($guides)
        ->additional([
            'meta' => [
                'total_page' => (int) ceil($guides->total() / $guides->perPage()),
            ],
        ])
        ->response()
        ->getData(), 'Meal List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'licence' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'contact' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'notes' => 'nullable|string',
            'day_rate' => 'nullable|integer|min:0',
            'renew_score' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'languages' => 'nullable|array',
            'languages.*' => 'string',
            'cities' => 'nullable|array',
            'cities.*' => 'exists:cities,id',
        ]);

        if ($request->hasFile('image')) {
            // $validated['image'] = $request->file('image')->store('guides', 'public');
            $fileData = $this->uploads($request->file('image'), 'images/');
            $validated['image'] = $fileData['fileName'];
        }
        if ($request->hasFile('licence')) {
            // $validated['image'] = $request->file('image')->store('guides', 'public');
            $fileData = $this->uploads($request->file('licence'), 'images/');
            $validated['licence'] = $fileData['fileName'];
        }

        $guide = Guide::create($validated);

        // Sync cities (cities) - this is the cleanest approach
        if ($request->has('cities')) {
            $guide->cities()->sync($request->cities);
        }

        return $this->success(new GuideResource($guide), 'Guide created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Guide $guide)
    {
        $guide->load('cities');

        return $this->success(new GuideResource($guide), 'Guide retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Guide $guide)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'licence' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'contact' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'notes' => 'nullable|string',
            'day_rate' => 'nullable|integer|min:0',
            'renew_score' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'languages' => 'nullable|array',
            'languages.*' => 'string',
            'cities' => 'nullable|array',
            'cities.*' => 'exists:cities,id',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($guide->image) {
                Storage::delete('images/' . $guide->image);
            }
            // $validated['image'] = $request->file('image')->store('guides', 'public');
            $fileData = $this->uploads($request->file('image'), 'images/');
            $validated['image'] = $fileData['fileName'];
        }

        if ($request->hasFile('licence')) {
            // Delete old licence
            if ($guide->licence) {
                Storage::delete('images/' . $guide->licence);
            }
            // $validated['image'] = $request->file('image')->store('guides', 'public');
            $fileData = $this->uploads($request->file('licence'), 'images/');
            $validated['licence'] = $fileData['fileName'];
        }

        $guide->update($validated);

        // Sync cities (cities) - this is the cleanest approach
        if ($request->has('cities')) {
            $guide->cities()->sync($request->cities);
        }

        return $this->success(new GuideResource($guide), 'Guide updated successfully.');
    }

    public function removeArea(Guide $guide, Request $request)
    {
        if (!$request->has('cities') || !is_array($request->cities)) {
            return $this->error('Invalid cities data.', 422);
        }

        $guide->cities()->detach($request->cities);

        return $this->success(null, 'Guide cities removed successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Guide $guide)
    {
        if ($guide->image) {
            // Storage::disk('public')->delete($guide->image);
            Storage::delete('images/' . $guide->image);
        }

        if ($guide->licence) {
            // Storage::disk('public')->delete($guide->licence);
            Storage::delete('images/' . $guide->licence);
        }

        $guide->delete();

        return $this->success(null, 'Guide deleted successfully.');
    }

    /**
     * Toggle guide active status.
     */
    public function toggleStatus(Guide $guide)
    {
        $guide->update(['is_active' => !$guide->is_active]);

        return $this->success(new GuideResource($guide), 'Guide status toggled successfully.');
    }
}
