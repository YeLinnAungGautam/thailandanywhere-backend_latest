<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashStructureResource;
use App\Models\CashStructure;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class CashStructureController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $limit = $request->query('limit', 10);
        $query = CashStructure::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($limit);

        return $this->success(CashStructureResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Cash Structure List');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $data = CashStructure::create($validated);

        return $this->success(new CashStructureResource($data), 'Successfully created');
    }

    public function update(Request $request, string $id)
    {
        $find = CashStructure::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'code' => $request->code ?? $find->code,
            'description' => $request->description ?? $find->description
        ];


        $find->update($data);

        return $this->success(new CashStructureResource($find), 'Successfully updated');
    }

    public function destroy(string $id)
    {
        $find = CashStructure::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }
        $find->delete();
        return $this->success(null, 'Successfully deleted');
    }
}
