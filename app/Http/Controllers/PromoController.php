<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromoRequest;
use App\Http\Requests\UpdatePromoRequest;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PromoController extends Controller
{
     use HttpResponses;
    use ImageManager;
    /**
     * Build the applicable_products JSON from request input.
     */
    private function buildApplicableProducts(Request $request): array
    {
        if ($request->input('promo_applies_to') !== 'specific') {
            return [];
        }

        $map = [
            'hotel'           => [$request->boolean('all_hotels'), $request->input('hotel_ids', [])],
            'entrance_ticket' => [$request->boolean('all_entrance_tickets'), $request->input('entrance_ticket_ids', [])],
            'vantour'         => [$request->boolean('all_vantours'), $request->input('vantour_ids', [])],
            'inclusive'       => [$request->boolean('all_inclusive'), $request->input('inclusive_ids', [])],
        ];

        $applicableProducts = [];

        foreach ($map as $key => [$allFlag, $ids]) {
            if ($allFlag) {
                $applicableProducts[$key] = 'all';
            } elseif (! empty($ids)) {
                $applicableProducts[$key] = array_values(array_map('intval', $ids));
            }
        }

        return $applicableProducts;
    }

    // GET /admin/promos
    public function index(Request $request)
    {
        $query = Promo::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('promo_name', 'like', '%' . $request->search . '%')
                  ->orWhere('promo_code', 'like', '%' . $request->search . '%');
            });
        }

        $data = $query->latest()->paginate(20);

        // return response()->json($query->latest()->paginate(20));
        return $this->success(PromoResource::collection($data)
        ->additional([
            'meta' => [
                'total_page' => (int) ceil($data->total() / $data->perPage()),
            ],
        ])
        ->response()
        ->getData(), 'Room List');
    }

    // POST /admin/promos
    public function store(StorePromoRequest $request)
    {
        $validated = $request->validated();

        $image = '';

        if ($file = $request->file('image')) {
            $fileData = $this->uploads($file, 'images/');
            $image = $fileData['fileName'];
        }

        $promo = Promo::create([
            'promo_name'          => $validated['promo_name'],
            'promo_des'           => $validated['promo_des'] ?? null,
            'promo_code'          => $validated['promo_code'],
            'promo_type'          => $validated['promo_type'],
            'promo_amount'        => $validated['promo_amount'],
            'promo_count'         => $validated['promo_count'],
            'promo_active'        => $validated['promo_active'] ?? true,
            'promo_start_date'    => $validated['promo_start_date'] ?? null,
            'promo_end_date'      => $validated['promo_end_date'],
            'promo_applies_to'    => $validated['promo_applies_to'],
            'applicable_products' => $this->buildApplicableProducts($request),
            'image'               => $image
        ]);

        // return response()->json($promo, 201);
        return $this->success(new PromoResource($promo), 'Successfully created', 200);
    }

    // GET /admin/promos/{promo}
    public function show(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);
        // return response()->json($promo->load('usages'));
        $data = $promo->load('usages');
        return $this->success(new PromoResource($data), 'Successfully get detail', 200);
    }

    // PUT/PATCH /admin/promos/{promo}
    public function update(UpdatePromoRequest $request, $id)
    {
        $validated = $request->validated();

        $promo = Promo::findOrFail($id);

        // Build the base update payload first
        $updateData = collect($validated)->only([
            'promo_name', 'promo_des', 'promo_code', 'promo_type', 'promo_amount',
            'promo_count', 'promo_active', 'promo_start_date', 'promo_end_date', 'promo_applies_to',
        ])->toArray();

        if ($request->has('promo_applies_to')) {
            $updateData['applicable_products'] = $this->buildApplicableProducts($request);
        }

        // Handle new image upload
        if ($file = $request->file('image')) {
            if ($promo->image) {
                Storage::delete('images/' . $promo->image);
            }

            $fileData = $this->uploads($file, 'images/');
            $updateData['image'] = $fileData['fileName'];
        } elseif ($request->boolean('remove_image') && $promo->image) {
            // Only clear the image if no new file was uploaded and removal was explicitly requested
            Storage::delete('images/' . $promo->image);
            $updateData['image'] = null;
        }

        $promo->update($updateData);

        return $this->success(new PromoResource($promo), 'Successfully updated', 200);
    }

    // DELETE /admin/promos/{promo}
    public function destroy($id)
    {
        $promo = Promo::findOrFail($id);
        $promo->delete();

        // return response()->json(['message' => 'Promo deleted']);
        return $this->success(null, 'Successfully deleted', 200);
    }
}
