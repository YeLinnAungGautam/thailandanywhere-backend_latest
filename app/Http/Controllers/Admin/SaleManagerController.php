<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Illuminate\Http\Request;

class SaleManagerController extends Controller
{
    use ImageManager;
    use HttpResponses;

    public function index()
    {
        $auth_user = auth()->user();

        $data = $auth_user->saleManagers()->paginate();

        return $this->success(AdminResource::collection($data)
            ->additional([
                'meta' => [
                    'total_page' => (int)ceil($data->total() / $data->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'Sale Manager List');
    }

    public function assign(Request $request)
    {
        $auth_user = auth()->user();

        $this->validate($request, [
            'sale_manager_ids' => 'required', // format - 1,2,3
        ]);

        $auth_user->saleManagers()->sync(explode(',', $request->sale_manager_ids));

        $data = $auth_user->saleManagers()->get();

        return $this->success(AdminResource::collection($data)
            ->response()
            ->getData(), 'Sale Manager List');
    }
}
