<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarResource;
use App\Models\Car;
use Illuminate\Http\Request;

class CarController extends Controller
{
    public function index(Request $request)
    {
        return CarResource::collection(Car::query()->paginate($request->limit ?? 10))->additional(['result' => 1, 'message' => 'success']);
    }
}
