<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaleManagerController extends Controller
{
    use ImageManager;
    use HttpResponses;

    public function index()
    {
        $auth_user = auth()->user();

        $data = Admin::whereHas('subsidiaries')->with('subsidiaries')->paginate();

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
        try {
            $auth_user = auth()->user();

            if (!$auth_user->isSuperAdmin()) {
                return $this->error('You are not authorized to access this route.', 403);
            }

            $this->validate($request, [
                'sale_manager_id' => 'required',
                'subsidiary_ids' => 'required', // format - 1,2,3
            ]);

            $sale_manager = Admin::query()
                ->where('id', $request->sale_manager_id)
                ->with('subsidiaries')
                ->first();

            if (!$sale_manager || !$sale_manager->isSaleManager()) {
                return $this->error('Sale Manager not found.', 404);
            }

            $sale_manager->subsidiaries()->sync(explode(',', $request->subsidiary_ids));

            return $this->success(new AdminResource($sale_manager), 'Sale Manager assigned successfully.');
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return $this->error($e->getMessage(), 500);
        }
    }
}
