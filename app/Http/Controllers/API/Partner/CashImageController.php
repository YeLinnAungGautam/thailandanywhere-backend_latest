<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageDetailResource;
use App\Models\CashImage;
use App\Services\CashImagePartnerService;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class CashImageController extends Controller
{
    use HttpResponses;

    protected $cashImagePartnerService;

    public function __construct(
        CashImagePartnerService $cashImagePartnerService,
    ) {
        $this->cashImagePartnerService = $cashImagePartnerService;
    }

    public function index(Request $request)
    {

        $result = $this->cashImagePartnerService->getList($request);

        if ($result['success']) {
            return response()->json([
                'status' => 1,
                'message' => $result['message'],
                'result' => $result['data']
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => $result['message'],
                'result' => null
            ], $result['error_type'] === 'validation' ? 422 : 500);
        }
    }
    public function show(string $id)
    {
        $find = CashImage::query()
            ->with(['relatable', 'bookings'])
            ->find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new CashImageDetailResource($find), 'Successfully retrieved');
    }
}
