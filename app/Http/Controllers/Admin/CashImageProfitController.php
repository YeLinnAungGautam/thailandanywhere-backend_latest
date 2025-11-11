<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CashImageProfitService;
use Illuminate\Http\Request;

class CashImageProfitController extends Controller
{
    protected $cashImageProfitService;

    public function __construct(CashImageProfitService $cashImageProfitService)
    {
        $this->cashImageProfitService = $cashImageProfitService;
    }

    /**
     * Get cash images with booking filters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $result = $this->cashImageProfitService->generateProfitReport($request);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function getBookingItemsByDate(Request $request)
    {
        $result = $this->cashImageProfitService->getBookingItemsByDate($request);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
