<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAddon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductAddonController extends Controller
{
    public function index(Request $request)
    {
        $addons = ProductAddon::query()
            ->when($request->search, fn ($query) => $query->where('name', 'like', "%{$request->search}%"))
            ->paginate($request->limit ?? 10);

        return success($addons);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_type' => 'required',
                'name' => 'required',
                'description' => 'nullable',
                'price' => 'required',
                'cost_price' => 'nullable',
            ]);

            $addon = ProductAddon::create([
                'product_type' => $validated['product_type'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'cost_price' => $validated['cost_price'] ?? null,
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
                'product_type' => 'required',
                'name' => 'required',
                'description' => 'nullable',
                'price' => 'required',
                'cost_price' => 'nullable',
            ]);

            $addon = ProductAddon::find($id);

            $addon->update([
                'product_type' => $validated['product_type'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'cost_price' => $validated['cost_price'] ?? null,
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
}
