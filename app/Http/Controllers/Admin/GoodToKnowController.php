<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoodToKnow;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GoodToKnowController extends Controller
{
    use HttpResponses;
    /**
     * Store multiple newly created good to knows.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'knowable_type' => 'required|string',
            'knowable_id' => 'required|integer',
            'items' => 'required|array|min:1|max:10',
            'items.*.title' => 'required|string|max:255',
            'items.*.description_mm' => 'required|string',
            'items.*.description_en' => 'required|string',
            'items.*.icon' => 'nullable|string|max:255',
            'items.*.order' => 'nullable|integer',
            'items.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first(), 422);
        }

        $createdItems = [];

        DB::beginTransaction();
        try {
            foreach ($request->items as $itemData) {
                $data = [
                    'knowable_type' => $request->knowable_type,
                    'knowable_id' => $request->knowable_id,
                    'title' => $itemData['title'],
                    'description_mm' => $itemData['description_mm'],
                    'description_en' => $itemData['description_en'],
                    'icon' => $itemData['icon'] ?? null,
                    'order' => $itemData['order'] ?? 0,
                    'is_active' => $itemData['is_active'] ?? true,
                ];

                $goodToKnow = GoodToKnow::create($data);
                $createdItems[] = $goodToKnow;
            }

            DB::commit();

            $this->success('Good to know items created successfully', $createdItems, 201);

        } catch (\Exception $e) {
            DB::rollBack();


            return $this->error('Failed to create good to know items', 500);
        }
    }

    /**
     * Update the specified good to know.
     */
    public function update(Request $request, $id)
    {
        $goodToKnow = GoodToKnow::find($id);

        if (!$goodToKnow) {

            return $this->error('Good to know item not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description_mm' => 'sometimes|required|string',
            'description_en' => 'sometimes|required|string',
            'knowable_type' => 'sometimes|required|string',
            'knowable_id' => 'sometimes|required|integer',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {

            return $this->error($validator->errors()->first(), 422);
        }

        $data = $request->only([
            'title',
            'description_mm',
            'description_en',
            'knowable_type',
            'knowable_id',
            'icon',
            'order',
            'is_active'
        ]);

        $goodToKnow->update($data);


        return $this->success('Good to know item updated successfully', $goodToKnow);
    }

    /**
     * Remove the specified good to know.
     */
    public function destroy($id)
    {
        $goodToKnow = GoodToKnow::find($id);

        if (!$goodToKnow) {

            return $this->error('Good to know item not found', 404);
        }

        $goodToKnow->delete();


        return $this->success('Good to know item deleted successfully');
    }

    /**
     * Update order of good to know items.
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {

            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                GoodToKnow::where('id', $item['id'])->update(['order' => $item['order']]);
            }

            DB::commit();


            return $this->success('Order updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();


            return $this->error('Failed to update order', 500);
        }
    }
}
