<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttractionActivityResource;
use App\Models\AttractionActivity;
use Illuminate\Http\Request;

class AttractionActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $items = AttractionActivity::query()
            ->when($request->search, fn ($s_query) => $s_query->where('name', 'LIKE', "%{$request->search}%"))
            ->paginate($request->limit ?? 10);

        return AttractionActivityResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);

        $image = null;
        if ($request->file('image')) {
            $image = uploadFile($request->file('image'), 'images/attraction_activity/');
        }

        $activity = AttractionActivity::create([
            'name' => $request->name,
            'image' => $image
        ]);

        return success(new AttractionActivityResource($activity), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(AttractionActivity $attraction_activity)
    {
        return success(new AttractionActivityResource($attraction_activity), 'Detail');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AttractionActivity $attraction_activity)
    {
        $request->validate(['name' => 'required']);

        $image = $attraction_activity->image;
        if ($request->file('image')) {
            $image = uploadFile($request->file('image'), 'images/attraction_activity/');
        }

        $attraction_activity->update([
            'name' => $request->name,
            'image' => $image
        ]);

        return success(new AttractionActivityResource($attraction_activity), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttractionActivity $attraction_activity)
    {
        $attraction_activity->delete();

        return successMessage('Successfully deleted');
    }
}
