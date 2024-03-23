<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $suppliers = Supplier::with('drivers')
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->search}%");
            })
            ->paginate($request->limit ?? 20);

        return $this->success(SupplierResource::collection($suppliers)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($suppliers->total() / $suppliers->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Supplier List');
    }

    public function store(SupplierRequest $request)
    {
        try {
            $input = $request->validated();

            if ($request->file('logo')) {
                $input['logo'] = uploadFile($request->file('logo'), 'images/supplier/');
            }

            $supplier = Supplier::create($input);

            return $this->success(new SupplierResource($supplier), 'Successfully created', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $supplier = Supplier::find($id);

        if(is_null($supplier)) {
            return $this->error(null, 'Supplier not found.');
        }

        return $this->success(new SupplierResource($supplier), 'Supplier Detail', 200);
    }

    public function update(SupplierRequest $request, string $id)
    {
        try {
            $supplier = Supplier::find($id);

            if(is_null($supplier)) {
                throw new Exception('Supplier not found');
            }

            $input = $request->validated();

            if ($request->file('logo')) {
                $input['logo'] = uploadFile($request->file('logo'), 'images/supplier/');
            }

            $supplier->update($input);

            return $this->success(new SupplierResource($supplier), 'Successfully updated', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::find($id);

            if(is_null($supplier)) {
                throw new Exception('Supplier not found');
            }

            $supplier->delete();

            return $this->success(null, 'Successfully deleted', 200);
        } catch (Exception $e) {
            Log::error($e);

            return $this->error(null, $e->getMessage());
        }
    }
}
