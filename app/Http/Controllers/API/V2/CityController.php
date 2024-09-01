<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $data = City::query()
            ->when($request->search, fn ($query) => $query->where('name', 'LIKE', "%{$request->search}%"))
            ->paginate($request->limit ?? 10);

        return CityResource::collection($data)->additional(['result' => 1, 'message' => 'success']);
    }
}
