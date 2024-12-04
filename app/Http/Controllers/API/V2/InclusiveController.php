<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\InclusiveResource;
use App\Models\Inclusive;
use Illuminate\Http\Request;

class InclusiveController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');

        $query = Inclusive::query()
            ->with(['groupTours', 'entranceTickets', 'airportPickups', 'privateVanTours', 'airlineTickets', 'hotels']);

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return $this->success(InclusiveResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Inclusive List');
    }

    public function show(string $id)
    {
        $find = Inclusive::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new InclusiveResource($find), 'Inclusive Detail');
    }
}
