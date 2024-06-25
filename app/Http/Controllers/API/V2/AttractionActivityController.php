<?php
namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttractionActivityResource;
use App\Models\AttractionActivity;
use Illuminate\Http\Request;

class AttractionActivityController extends Controller
{
    public function index(Request $request)
    {
        $items = AttractionActivity::query()
            ->when($request->search, fn ($s_query) => $s_query->where('name', 'LIKE', "%{$request->search}%"))
            ->paginate($request->limit ?? 10);

        return AttractionActivityResource::collection($items)->additional(['result' => 1, 'message' => 'success']);
    }
}
