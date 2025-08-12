<?php

namespace App\Services;

use App\Http\Resources\Accountance\Detail\PrintResource;
use App\Http\Resources\Accountance\TaxReceiptResource;
use App\Models\TaxReceipt;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Exception;
use Illuminate\Support\Facades\Log;

class PrintPDFService
{
    /**
     * Validate the incoming request
     */
    private function validateRequest(Request $request)
    {
        if (!$request->has('date') || empty($request->date)) {
            throw new InvalidArgumentException('Date parameter is required');
        }
    }

    /**
     * Extract filters from request
     */
    private function extractFilters(Request $request)
    {
        return [
            'dates' => array_map('trim', explode(',', $request->date))
        ];
    }

    /**
     * Build optimized query
     */
    private function buildOptimizedQuery($filters)
    {
        $query = TaxReceipt::query()
            ->with('product', 'groups', 'groups.bookingItems', 'groups.customerDocuments')
            ->whereHas('groups');

        if (!empty($filters['dates'])) {
            $query->whereDate('service_start_date', '>=', $filters['dates'][0])
                  ->whereDate('service_start_date', '<=', $filters['dates'][1]);
        }

        return $query->orderBy('service_start_date', 'asc');
    }

    public function getExportCSVData(Request $request)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);
            $data = $query->get();

            return TaxReceiptResource::collection($data);
        } catch (Exception $e) {
            Log::error('Error getting export CSV data: ' . $e->getMessage());
            return collect([]);
        }
    }

    public function getTotalRecordsCount(Request $request)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);
            return $query->count();

        } catch (Exception $e) {
            Log::error('Error getting total records count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Batch အတွက် specific data ယူမယ် (offset နဲ့ limit နဲ့)
     */
    public function printPDFData(Request $request, int $offset, int $limit)
    {
        try {
            $this->validateRequest($request);
            $filters = $this->extractFilters($request);

            $query = $this->buildOptimizedQuery($filters);

            // Offset နဲ့ limit သုံးပြီး specific batch ယူမယ်
            $data = $query->offset($offset)
                         ->limit($limit)
                         ->get();

            $resourceCollection = PrintResource::collection($data);

            return [
                'result' => $resourceCollection->response()->getData(true),
                'batch_info' => [
                    'offset' => $offset,
                    'limit' => $limit,
                    'count' => $data->count(),
                    'from_record' => $offset + 1,
                    'to_record' => $offset + $data->count()
                ]
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'Error has occurred.',
                'message' => 'Validation Error: ' . $e->getMessage(),
                'result' => null
            ];
        } catch (Exception $e) {
            return [
                'status' => 'Error has occurred.',
                'message' => 'An error occurred while retrieving batch data. Error: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }
}
