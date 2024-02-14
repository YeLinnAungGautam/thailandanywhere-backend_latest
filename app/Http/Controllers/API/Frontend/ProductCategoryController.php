<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Frontend\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $product_categories = ProductCategory::query()->paginate($request->limit ?? 10);

        return ProductCategoryResource::collection($product_categories)->additional(['result' => 1, 'message' => 'success']);
    }
}
