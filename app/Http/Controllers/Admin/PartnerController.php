<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = Partner::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');
        $data = $query->paginate($limit);

        return $this->success(PartnerResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Partner List');

    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:225'],
            'email' => ['required', 'email', 'max:225', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'confirmed', 'max:225'],
        ]);

        $partner = Partner::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return $this->success($partner, 'Successfully created', 200);
    }

    public function show(Partner $partner)
    {
        return $this->success(new PartnerResource($partner), 'Partner Detail');
    }

    public function update(Request $request, Partner $partner)
    {
        $request->validate([
            'name' => ['string', 'max:225'],
            'email' => ['email', 'max:225', Rule::unique('partners', 'email')->ignore($partner)],
            'password' => ['string', 'confirmed', 'max:225'],
            'target_amount' => ['nullable', 'integer']
        ]);

        $partner->name = $request->name ?? $partner->name;
        $partner->email = $request->email ?? $partner->email;
        $partner->password = $request->password ? Hash::make($request->password) : $partner->password;
        $partner->update();

        return $this->success($partner, 'Successfully updated', 200);
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();

        return $this->success(null, 'Successfully deleted', 200);
    }

    public function assignProduct(Partner $partner, Request $request)
    {
        try {
            $request->validate([
                'product_type' => ['required', 'string', Rule::in([
                    'hotel',
                    'private_van_tour',
                    'entrance_ticket',
                    'group_tour',
                    'inclusive'
                ])],
                'product_ids' => ['required', 'string'], // 1,2,3,4,5 like this
            ]);

            $this->assignProductByType($partner, $request);

            return $this->success(null, 'Successfully assigned product', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error('Failed to assign product', 500);
        }
    }

    private function assignProductByType(Partner $partner, Request $request)
    {
        $product_type = $request->product_type;
        $product_ids = explode(',', $request->product_ids);

        switch ($product_type) {
            case 'hotel':
                $partner->hotels()->sync($product_ids);

                break;
            case 'private_van_tour':
                $partner->privateVanTours()->sync($product_ids);

                break;
            case 'entrance_ticket':
                $partner->entranceTickets()->sync($product_ids);

                break;
            case 'group_tour':
                $partner->groupTours()->sync($product_ids);

                break;
            case 'inclusive':
                $partner->inclusiveProducts()->sync($product_ids);

                break;
            default:
                return $this->error('Invalid product type', 400);
        }
    }
}
