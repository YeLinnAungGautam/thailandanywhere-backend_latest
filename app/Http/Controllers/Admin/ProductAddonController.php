<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntranceTicketVariation;
use App\Models\ProductAddon;
use App\Models\Room;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductAddonController extends Controller
{
    public function index()
    {
        $addons = ProductAddon::all();

        return success($addons);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required',
                'price' => 'required',
                'product_id' => 'required',
                'description' => 'nullable',
            ]);

            $addon = ProductAddon::create([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'description' => $validated['description'] ?? null,
                'productable_id' => $validated['product_id'],
                'productable_type' => $this->getProductType($request->product_type),
            ]);

            return success($addon);
        } catch (Exception $e) {
            Log::error($e);

            return failedMessage($e->getMessage());
        }
    }

    public function update(string $id, Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required',
                'price' => 'required',
                'product_id' => 'required',
                'description' => 'nullable',
            ]);

            $addon = ProductAddon::find($id);

            $addon->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'description' => $validated['description'] ?? null,
                'productable_id' => $validated['product_id'],
                'productable_type' => $this->getProductType($request->product_type),
            ]);

            return success($addon);
        } catch (Exception $e) {
            Log::error($e);

            return failedMessage($e->getMessage());
        }
    }

    public function destroy(ProductAddon $productAddon)
    {
        $productAddon->delete();

        return success('Product addon deleted successfully');
    }

    public function getProductType(string $product_type)
    {
        switch ($product_type) {
            case 'room':
                return Room::class;

                break;

            case 'entrance_ticket_variation':
                return EntranceTicketVariation::class;

                break;

            default:
                throw new Exception('Product type is not found');

                break;
        }
    }
}
