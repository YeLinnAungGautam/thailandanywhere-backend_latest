<?php

namespace App\Http\Controllers\Accountance;

use App\Exports\CashImageExport;
use App\Exports\CashInvoiceExport;
use App\Exports\CashParchaseExport;
use App\Exports\CashParchaseTaxExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageDetailResource;
use App\Http\Resources\Accountance\CashImageResource;
use App\Jobs\GenerateCashImagePdfJob;
use App\Jobs\GenerateCashParchasePdfJob;
use App\Models\CashImage;
use App\Services\CashImageService;
use App\Services\PrintPDFService;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CashImageController extends Controller
{
    use HttpResponses;
    use ImageManager;

    protected $cashImageService;
    protected $csvExportService;
    protected $printPDFService;

    public function __construct(
        CashImageService $cashImageService,
        PrintPDFService $printPDFService
    ) {
        $this->cashImageService = $cashImageService;
        $this->printPDFService = $printPDFService;
    }
    public function index(Request $request)
    {

        $result = $this->cashImageService->getAll($request);

        if ($result['success']) {
            return response()->json([
                'status' => 'Request was successful.',
                'message' => $result['message'],
                'result' => $result['data']
            ]);
        } else {
            return response()->json([
                'status' => 'Error has occurred.',
                'message' => $result['message'],
                'result' => null
            ], $result['error_type'] === 'validation' ? 422 : 500);
        }
    }

    public function summary(Request $request)
    {

        $result = $this->cashImageService->getAllSummary($request);

        if ($result['status'] == 1) {
            return response()->json([
                'status' => 1,
                'message' => $result['message'],
                'result' => $result['result']
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => $result['message'],
                'result' => null
            ], $result['error_type'] === 'validation' ? 422 : 500);
        }
    }

    public function store(Request $request)
    {
        $validated = request()->validate([
            'image' => 'required',
            'date' => 'required|date_format:Y-m-d H:i:s',
            'sender' => 'required|string|max:255',
            'reciever' => 'required|string|max:255', // Fixed spelling
            'amount' => 'required|numeric|min:0',
            'interact_bank' => 'nullable|string|max:255',
            'currency' => 'required|string|max:10',
            'relatable_type' => 'required',
            'relatable_id' => 'required'
        ]);

        // Since image is required, no need to check if empty
        $fileData = $this->uploads($validated['image'], 'images/');

        $create = CashImage::create([
            'image' => $fileData['fileName'],
            'date' => $validated['date'],
            'sender' => $validated['sender'],
            'receiver' => $validated['reciever'], // Fixed spelling
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'interact_bank' => $validated['interact_bank'] ?? null,
            'relatable_type' => $validated['relatable_type'],
            'relatable_id' => $validated['relatable_id'],
            'image_path' => $fileData['filePath'],
        ]);

        return $this->success(new CashImageResource($create), 'Successfully created');
    }

    public function show(string $id)
    {
        $find = CashImage::find($id)->load(['relatable', 'bookings']);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        return $this->success(new CashImageDetailResource($find), 'Successfully retrieved');
    }

    public function update(Request $request, string $id)
    {
        $find = CashImage::find($id);

        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }

        $data = [
            'date' => $request->date ?? $find->date,
            'sender' => $request->sender ?? $find->sender,
            'receiver' => $request->reciever ?? $find->reciever,
            'amount' => $request->amount ?? $find->amount,
            'currency' => $request->currency ?? $find->currency,
            'interact_bank' => $request->interact_bank ?? $find->interact_bank,
            'relatable_type' => $request->relatable_type ?? $find->relatable_type,
            'relatable_id' => $request->relatable_id ?? $find->relatable_id,
        ];

        $find->update($data);

        return $this->success(new CashImageResource($find), 'Successfully updated');
    }

    public function destroy(string $id)
    {
        $find = CashImage::find($id);
        if (!$find) {
            return $this->error(null, 'Data not found', 404);
        }
        Storage::delete('images/' . $find->image);
        $find->delete();

        return $this->success(null, 'Successfully deleted');
    }

    public function exportSummaryToCsv(Request $request)
    {
        try {
            $file_name = "cash_image_export_" . date('Y-m-d-H-i-s') . ".csv";

            // Pass request parameters to the export class
            $export = new CashImageExport($request->all());

            // Check if there's any data to export
            if ($export->collection()->isEmpty()) {
                return $this->error(null, 'No data available for export', 404);
            }

            \Excel::store($export, "export/" . $file_name);

            return $this->success(
                ['download_link' => get_file_link('export', $file_name)],
                'CSV export is successful',
                200
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function exportParchaseToCsv(Request $request)
    {
        try {
            // $data = $this->cashImageService->getAllParchaseForExport($request);
            // return $this->success($data, 'CSV export is successful', 200);
            $file_name = "cash_parchase_export_" . date('Y-m-d-H-i-s') . ".csv";

            $export = new CashParchaseExport($request->all());

            // Check if there's any data to export
            if ($export->collection()->isEmpty()) {
                return $this->error(null, 'No data available for export', 404);
            }

            \Excel::store($export, "export/" . $file_name);

            return $this->success(
                ['download_link' => get_file_link('export', $file_name)],
                'CSV export is successful',
                200
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function exportParchaseTaxToCsv(Request $request)
    {
        try {
            // $data = $this->printPDFService->getExportCSVData($request);
            // return response()->json([
            //     'data' => $data
            // ]);
            $file_name = "cash_parchase_export_" . date('Y-m-d-H-i-s') . ".csv";

            $export = new CashParchaseTaxExport($request->all());

            // Check if there's any data to export
            if ($export->collection()->isEmpty()) {
                return $this->error(null, 'No data available for export', 404);
            }

            \Excel::store($export, "export/" . $file_name);

            return $this->success(
                ['download_link' => get_file_link('export', $file_name)],
                'CSV export is successful',
                200
            );
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function exportInvoiceToCsv(Request $request)
    {
        try {
            // Get total count first
            $count = $this->cashImageService->getTotalRecordsCount($request);

            // $result = $this->cashImageService->getAllParchaseLimitForExport($request);

            // return response()->json([
            //     'data' => $result
            // ]);

            // If no data, return error
            if ($count == 0) {
                return $this->error(null, 'No data available for export', 404);
            }

            $limit = 50; // Records per file
            $totalChunks = ceil($count / $limit);
            $downloadLinks = [];

            // If total records are 50 or less, create single file
            if ($count <= $limit) {
                $file_name = "cash_invoice_export_" . date('Y-m-d-H-i-s') . ".csv";

                $export = new CashInvoiceExport($request->all());

                if (!$export->collection()->isEmpty()) {
                    \Excel::store($export, "export/" . $file_name);

                    return $this->success([
                        'total_records' => $count,
                        'download_link' => get_file_link('export', $file_name)
                    ], 'CSV export is successful');
                }
            }

            // Generate multiple CSV files in chunks
            for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
                $offset = $chunk * $limit;

                // Clone request and add pagination parameters
                $chunkParams = $request->all();
                $chunkParams['limit'] = $limit;
                $chunkParams['offset'] = $offset;

                $file_name = "cash_invoice_export_" . date('Y-m-d-H-i-s') . "_part_" . ($chunk + 1) . "_of_" . $totalChunks . ".csv";

                $export = new CashInvoiceExport($chunkParams);

                // Check if this chunk has data
                if (!$export->collection()->isEmpty()) {
                    \Excel::store($export, "export/" . $file_name);

                    $downloadLinks[] = [
                        'file_name' => $file_name,
                        'download_link' => get_file_link('export', $file_name),
                        'part' => $chunk + 1,
                        'total_parts' => $totalChunks,
                        'records_in_file' => min($limit, $count - $offset)
                    ];
                }
            }

            return $this->success([
                'total_records' => $count,
                'total_files' => count($downloadLinks),
                'records_per_file' => $limit,
                'files' => $downloadLinks
            ], 'CSV export completed successfully - ' . count($downloadLinks) . ' files generated');

        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function printCashImage(Request $request)
    {
        try {
            $jobId = "cash_image_pdf_" . date('Y-m-d-H-i-s');

            // Dispatch the PDF generation job
            GenerateCashImagePdfJob::dispatch($request->all(), $jobId)
                ->onQueue('pdf_generation'); // Optional: specific queue

            return response()->json([
                'success' => true,
                'message' => 'PDF generation started in background',
                'job_id' => $jobId,
                'status_url' => url("/admin/pdf-status/{$jobId}"),
                'estimated_time' => 'This may take 2-5 minutes for large datasets'
            ], 202); // 202 = Accepted (processing)

        } catch (Exception $e) {
            Log::error('PDF Job Dispatch Error: ' . $e->getMessage());

            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function checkPdfStatus($jobId)
    {
        $status = Cache::get("pdf_job_{$jobId}");

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or expired'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'status' => $status['status'],
            'download_url' => $status['download_url'] ?? null,
            'filename' => $status['filename'] ?? null,
            'error' => $status['error'] ?? null,
            'progress' => $status['progress'] ?? null
        ]);
    }

    public function printCashParchaseImage(Request $request)
    {
        try {
            // Total records ရေ ရမယ်
            $totalRecords = $this->cashImageService->getTotalRecordsCount($request);

            // $data = $this->cashImageService->getAllPurchaseForPrintBatch($request, 0, 100);

            // return response()->json([
            //     'data' => $data
            // ]);

            if ($totalRecords === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found to generate PDF'
                ], 404);
            }

            $batchSize = 50; // တစ်ခါမှာ 50 items ပဲ
            $totalBatches = ceil($totalRecords / $batchSize);

            // Job ID generate လုပ်မယ်
            $jobId = "cash_batch_pdf_" . date('Y-m-d-H-i-s');

            // Batch status initialize လုပ်မယ်
            Cache::put("batch_pdf_job_{$jobId}", [
                'status' => 'processing',
                'total_records' => $totalRecords,
                'total_batches' => $totalBatches,
                'completed_batches' => 0,
                'batch_files' => [],
                'created_at' => now(),
                'progress' => 0
            ], 7200); // 2 hours

            // ပထမ batch ကို စမယ်
            $this->dispatchNextBatch($request->all(), $jobId, 0, $batchSize, 1, $totalBatches);

            return response()->json([
                'success' => true,
                'message' => 'PDF batch generation started',
                'job_id' => $jobId,
                'total_records' => $totalRecords,
                'total_batches' => $totalBatches,
                'batch_size' => $batchSize,
                'status_url' => url("/admin/pdf-batch-status/{$jobId}"),
                'estimated_time' => 'Each batch takes 1-2 minutes'
            ], 202);

        } catch (Exception $e) {
            Log::error('PDF Batch Job Dispatch Error: ' . $e->getMessage());
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    private function dispatchNextBatch($requestData, $jobId, $offset, $batchSize, $batchNumber, $totalBatches)
    {
        GenerateCashParchasePdfJob::dispatch(
            $requestData,
            $jobId,
            $offset,
            $batchSize,
            $batchNumber,
            $totalBatches
        )->onQueue('pdf_generation');
    }

    public function checkPdfBatchStatus($jobId)
    {
        $status = Cache::get("batch_pdf_job_{$jobId}");

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or expired'
            ], 404);
        }

        // Progress calculate လုပ်မယ်
        $progress = 0;
        if ($status['total_batches'] > 0) {
            $progress = ($status['completed_batches'] / $status['total_batches']) * 100;
        }

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'status' => $status['status'],
            'progress' => round($progress, 1),
            'total_records' => $status['total_records'],
            'total_batches' => $status['total_batches'],
            'completed_batches' => $status['completed_batches'],
            'batch_files' => $status['batch_files'], // အားလုံး files list
            'created_at' => $status['created_at'],
            'updated_at' => $status['updated_at'] ?? null
        ]);
    }
}
