<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminMetaResource;
use App\Models\AdminMeta;
use Illuminate\Http\Request;

class AdminMetaController extends Controller
{
    public function index(Request $request)
    {
        $meta = AdminMeta::where('meta_key', 'sale_target')->first();

        return success(new AdminMetaResource($meta));
    }

    public function storeSaleTarget(Request $request)
    {
        $validated = $request->validate([
            'daily_target' => 'required',
            'daily_getting_close' => 'required',
            'daily_keep_going' => 'required',

            'monthly_target' => 'required',
            'monthly_getting_close' => 'required',
            'monthly_keep_going' => 'required',
        ]);

        $meta = $request->user()->metas()->updateOrCreate(
            ['meta_key' => 'sale_target'],
            ['meta_value' => json_encode($validated)]
        );

        return success(new AdminMetaResource($meta));
    }
}
