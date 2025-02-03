<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\InclusiveListResource;
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
            ->with('InclusiveDetails')
            ->when($request->lead_days, function ($query) use ($request) {
                $query->where('day', $request->lead_days)->where('night', $request->lead_days + 1);
            })
            ->when($request->destinations, function ($query) use ($request) {
                $query->whereHas('InclusiveDetails', function ($query) use ($request) {
                    $query->whereHas('destinations', function ($query) use ($request) {
                        $query->whereIn('destination_id', explode(',', $request->destinations));
                    });
                });
            })
            ->when($request->cities, function ($query) use ($request) {
                $query->whereHas('InclusiveDetails', function ($query) use ($request) {
                    $query->whereHas('cities', function ($query) use ($request) {
                        $query->whereIn('city_id', explode(',', $request->cities));
                    });
                });
            });

        if ($search) {
            $query->where('name', 'LIKE', "{$search}%");
        }

        $query->orderBy('created_at', 'desc');

        $data = $query->paginate($limit);

        return InclusiveListResource::collection($data)->additional(['result' => 1, 'message' => 'success']);
    }

    public function show(string $id)
    {
        $find = Inclusive::find($id);

        if (!$find) {
            return failedMessage('Data not found');
        }

        return success(new InclusiveResource($find));
    }
}
