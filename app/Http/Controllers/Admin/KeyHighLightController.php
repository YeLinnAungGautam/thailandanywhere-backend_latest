<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KeyHighlight;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class KeyHighLightController extends Controller
{
     use ImageManager;
    use HttpResponses;

    /**
     * Store multiple newly created key highlights.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'highlights' => 'required|array|min:1|max:5',
            'highlights.*.title' => 'required|string|max:255',
            'highlights.*.highlightable_type' => 'required|string',
            'highlights.*.highlightable_id' => 'required|integer',
            'highlights.*.description_mm' => 'required|string',
            'highlights.*.description_en' => 'required|string',
            'highlights.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'highlights.*.order' => 'nullable|integer',
            'highlights.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {

            return $this->error($validator->errors()->first(), 422);
        }

        $createdHighlights = [];

        DB::beginTransaction();
        try {
            foreach ($request->highlights as $index => $highlightData) {
                $data = [
                    'title' => $highlightData['title'],
                    'description_mm' => $highlightData['description_mm'],
                    'description_en' => $highlightData['description_en'],
                    'highlightable_type' => $highlightData['highlightable_type'],
                    'highlightable_id' => $highlightData['highlightable_id'],
                    'order' => $highlightData['order'] ?? null,
                    'is_active' => $highlightData['is_active'] ?? true,
                ];

                // Handle image upload for each item
                if ($request->hasFile("highlights.{$index}.image")) {
                    $image = $request->file("highlights.{$index}.image");
                    $fileData = $this->uploads($image, 'images/');
                    $data['image_url'] = $fileData['fileName'];
                }

                $highlight = KeyHighlight::create($data);
                $createdHighlights[] = $highlight;
            }

            DB::commit();


            return $this->success('Key highlights created successfully', $createdHighlights, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create key highlights',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $highlight = KeyHighlight::find($id);

        if (!$highlight) {

            return $this->error('Key highlight not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description_mm' => 'sometimes|required|string',
            'description_en' => 'sometimes|required|string',
            'highlightable_type' => 'sometimes|required|string',
            'highlightable_id' => 'sometimes|required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {

            return $this->error($validator->errors()->first(), 422);
        }

        $data = $request->only(['title', 'description_mm', 'description_en', 'order', 'is_active', 'highlightable_type', 'highlightable_id']);



        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($highlight->image_url) {
                Storage::delete('images/' . $highlight->image_url);
            }

            $image = $request->file('image');
            $fileData = $this->uploads($image, 'images/');
            $data['image_url'] = $fileData['fileName'];
        }

        $highlight->update($data);


        return $this->success('Key highlight updated successfully', $highlight,201);
    }

    public function destroy($id)
    {
        $highlight = KeyHighlight::find($id);

        if (!$highlight) {

            return $this->error('Key highlight not found', 404);
        }

        // Delete image if exists
        if ($highlight->image_url) {
            Storage::delete('images/' . $highlight->image_url);
        }

        $highlight->delete();


        return $this->success('Key highlight deleted successfully');
    }

    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:key_highlights,id',
            'items.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {

            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                KeyHighlight::where('id', $item['id'])->update(['order' => $item['order']]);
            }

            DB::commit();


            return $this->success('Order updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();


            return $this->error('Failed to update order', 500);
        }
    }
}
