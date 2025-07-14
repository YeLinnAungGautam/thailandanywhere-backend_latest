<?php

namespace App\Http\Controllers\Accountance;

use App\Http\Controllers\Controller;
use App\Services\BookingFinancialService;
use Illuminate\Http\Request;

class VatCalculationController extends Controller
{
    protected $financialService;

    public function __construct(BookingFinancialService $financialService)
    {
        $this->financialService = $financialService;
    }


    public function getMonthlySummary(Request $request)
    {
        $request->validate([
            'date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2},\d{4}-\d{2}-\d{2}$/',
        ]);

        $dateRange = $request->query('date');

        $summary = $this->financialService->getMonthlyFinancialSummary($dateRange);

        if ($summary['success']) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'date_range' => $dateRange,
                    'total_commission' => $summary['total_commission'],
                    'really_output_vat' => $summary['really_output_vat'],
                    'current_output_vat' => $summary['current_output_vat'], // This is for items WITH confirm letter
                    'total_output_vat' => $summary['total_output_vat'], // This is for items WITHOUT confirm letter
                ],
                'message' => $summary['message'],
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $summary['message'],
            ], 500);
        }
    }
}
