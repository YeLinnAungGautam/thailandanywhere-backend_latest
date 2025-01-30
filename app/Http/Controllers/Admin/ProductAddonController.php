<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddOnResource;
use App\Models\EntranceTicket;
use App\Models\Hotel;
use App\Models\PrivateVanTour;
use App\Models\ProductAddon;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductAddonController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $addons = ProductAddon::query()
            ->when($request->search, fn ($query) => $query->where('name', 'like', "%{$request->search}%"))
            ->when($request->product_type, fn ($query) => $query->where('productable_type', $this->getProductType($request->product_type)))
            ->when($request->product_id, fn ($query) => $query->where('productable_id', $request->product_id))
            ->paginate($request->limit ?? 10);

        return $this->success(AddOnResource::collection($addons)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($addons->total() / $addons->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Addon List List');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_type' => 'required',
                'product_id' => 'required',
                'name' => 'required',
                'description' => 'nullable',
                'price' => 'required',
                'cost_price' => 'nullable',
            ]);

            $addon = ProductAddon::create([
                'productable_type' => $this->getProductType($validated['product_type']),
                'productable_id' => $validated['product_id'],
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
                'name' => 'required',
                'description' => 'nullable',
                'price' => 'required',
                'cost_price' => 'nullable',
            ]);

            $addon = ProductAddon::find($id);

            if (!$addon) {
                return failedMessage('Product addon not found');
            }

            $addon->update([
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

    public function getProductType(string $product_type)
    {
        switch ($product_type) {
            case 'hotel':
                return Hotel::class;

                break;

            case 'entrance_ticket':
                return EntranceTicket::class;

                break;

            case 'private_van_tour':
                return PrivateVanTour::class;

                break;

            default:
                throw new Exception('Product type is not found');

                break;
        }
    }
}
