<?php

namespace App\Http\Controllers\Accountance;

use App\Exports\CashImageExport;
use App\Exports\CashParchaseExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Accountance\CashImageDetailResource;
use App\Http\Resources\Accountance\CashImageResource;
use App\Jobs\GenerateCashImagePdfJob;
use App\Jobs\GenerateCashParchasePdfJob;
use App\Models\CashImage;
use App\Services\CashImageService;
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

    public function __construct(
        CashImageService $cashImageService,
    ) {
        $this->cashImageService = $cashImageService;
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

    public function printCashImage(Request $request)
    {
        try {

            // $data = $this->cashImageService->onlyImages($request);
            // return response()->json([
            //     'success' => true,
            //     'data' => $data,
            //     'message' => 'Data retrieved successfully'
            // ]);
            // Generate unique job ID
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

    public function printCashParchaseImage(Request $request)
    {
        try {

            // $data = $this->cashImageService->getAllParchaseForPrint($request);
            // return response()->json([
            //     'data' => $data
            // ]);
            // Generate unique job ID
            $jobId = "cash_image_pdf_" . date('Y-m-d-H-i-s');

            // Dispatch the PDF generation job
                GenerateCashParchasePdfJob::dispatch($request->all(), $jobId)
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
}
