<?php

namespace App\Http\Controllers;

use App\Http\Resources\CaseResource;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CaseTable;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CaseController extends Controller
{
    use HttpResponses;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $search_crm_id = $request->query('search_crm_id');
        $search_type = $request->query('search_type');
        $status = $request->query('status');

        $query = CaseTable::query();

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(detail) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        if ($search_crm_id) {
            $query->where('related_id', $search_crm_id);
        }

        if ($search_type) {
            $query->where('case_type', $search_type);
        }

        if ($status) {
            $query->where('verification_status', $status);
        }

        $query->with('related');

        $cases = $query->latest()->paginate($limit);

        return $this->success(CaseResource::collection($cases)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($cases->total() / $cases->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Case List');
    }

    /**
     * Store a newly created case
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'related_id' => 'required|integer',
            'case_type' => 'required|in:sale,cost',
            'name' => 'required|string|max:255',
            'detail' => 'required|string',
            'verification_status' => 'required|in:verified,issue'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed.', 422);
        }

        // Verify the related_id exists in the appropriate table
        if ($request->case_type === 'sale') {
            if (!Booking::where('id', $request->related_id)->exists()) {
                return $this->error(['related_id' => 'Booking not found.'], 'Validation failed.', 404);
            }
        } else {
            if (!BookingItem::where('id', $request->related_id)->exists()) {
                return $this->error(['related_id' => 'Booking item not found.'], 'Validation failed.',404);
            }
        }

        $case = CaseTable::create($request->only([
            'related_id', 'case_type', 'name', 'detail', 'verification_status'
        ]));

        return $this->success(new CaseResource($case->load('related')), 'Case created successfully.');
    }

    /**
     * Update the specified case
     */
    public function update(Request $request, $id)
    {
        $case = CaseTable::find($id);

        if (!$case) {
            return $this->error(['id' => 'Case not found.'], 'Validation failed.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'detail' => 'sometimes|required|string',
            'verification_status' => 'sometimes|required|in:verified,issue'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Validation failed.', 422);
        }

        $case->update($request->only([
            'name', 'detail', 'verification_status'
        ]));
        return $this->success(new CaseResource($case->fresh()->load('related')), 'Case updated successfully.');
    }

    /**
     * Remove the specified case
     */
    public function destroy($id)
    {
        $case = CaseTable::find($id);

        if (!$case) {
            return $this->error(['id' => 'Case not found.'], 'Validation failed.', 404);
        }

        $case->delete();

        return $this->success(null, 'Case deleted successfully.');
    }
}
