<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    use ImageManager;
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = Customer::query()->with('bookings');

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $data = $query->paginate($limit);

        return $this->success(CustomerResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Customer List');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:225',
            'phone_number' => 'required|string|max:225',
        ]);

        $data = [
            'name' => $request->name,
            'company_name' => $request->company_name,
            'phone_number' => $request->phone_number,
            'line_id' => $request->line_id,
            'dob' => $request->dob,
            'nrc_number' => $request->nrc_number,
            'email' => $request->email,
            'comment' => $request->comment,
            'is_corporate_customer' => $request->is_corporate_customer ?? false,
        ];

        if ($file = $request->file('photo')) {
            $fileData = $this->uploads($file, 'images/');
            $data['photo'] = $fileData['fileName'];
        }

        $save = Customer::create($data);

        return $this->success(new CustomerResource($save), 'Successfully created');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $find = Customer::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new CustomerResource($find), 'Customer Detail');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $find = Customer::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'name' => $request->name ?? $find->name,
            'company_name' => $request->company_name ?? $find->company_name,
            'phone_number' => $request->phone_number ?? $find->phone_number,
            'line_id' => $request->line_id ?? $find->line_id,
            'dob' => $request->dob ?? $find->dob,
            'nrc_number' => $request->nrc_number ?? $find->nrc_number,
            'email' => $request->email ?? $find->email,
            'comment' => $request->comment ?? $find->comment,
            'is_corporate_customer' => $request->is_corporate_customer ?? $find->is_corporate_customer,
        ];

        if ($file = $request->file('photo')) {

            if ($find->photo) {
                Storage::delete('images/' . $find->photo);
            }

            $fileData = $this->uploads($file, 'images/');
            $data['photo'] = $fileData['fileName'];
        }

        $find->update($data);

        return $this->success(new CustomerResource($find), 'Successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $find = Customer::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        if ($find->photo) {
            Storage::delete('images/' . $find->photo);
        }

        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function getSales(string $customer_id)
    {
        $customer = Customer::find($customer_id);

        if(is_null($customer)) {
            return $this->error(null, 'Data not found', 404);
        }

        $bookings = $customer->bookings()->paginate(request('limit') ?? 10);

        return $this->success(BookingResource::collection($bookings)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($bookings->total() / $bookings->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Customer Booking List');
    }
}
