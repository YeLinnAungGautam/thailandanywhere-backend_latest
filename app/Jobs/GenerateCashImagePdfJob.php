<?php

namespace App\Jobs;

use App\Services\CashImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class GenerateCashImagePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestData;
    protected $jobId;
    protected $batchNumber;
    protected $totalBatches;

    public $timeout = 1800;
    public $maxExceptions = 3;
    public $tries = 3;

    public function __construct($requestData, $jobId, $batchNumber = 1, $totalBatches = 1)
    {
        $this->requestData = $requestData;
        $this->jobId = $jobId;
        $this->batchNumber = $batchNumber;
        $this->totalBatches = $totalBatches;

        Cache::put("pdf_job_{$this->jobId}", [
            'status' => 'queued',
            'created_at' => now(),
            'progress' => 0,
            'batch' => $batchNumber,
            'total_batches' => $totalBatches
        ], 7200);
    }

    public function handle(CashImageService $cashImageService)
    {
        try {
            $this->updateJobStatus('processing', "Processing batch {$this->batchNumber} of {$this->totalBatches}...", 10);

            $request = new Request($this->requestData);

            // Increase memory and time limits
            ini_set('memory_limit', '2048M'); // Increased from 1024M
            ini_set('max_execution_time', 1800);
            set_time_limit(1800);

            $this->updateJobStatus('processing', 'Fetching data from database...', 25);

            // This now only fetches 50 items due to batch_offset and batch_limit
            $data = $cashImageService->onlyImages($request);

            if (empty($data['result'])) {
                $this->updateJobStatus('failed', 'No data found to generate PDF');
                return;
            }

            $imageData = $data['result'];
            $totalItems = count($imageData);

            Log::info("Batch {$this->batchNumber}: Generating PDF for {$totalItems} items");

            $this->updateJobStatus('processing', "Generating PDF for {$totalItems} items in batch {$this->batchNumber}...", 50);

            // Clear any previous memory before PDF generation
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $pdf = Pdf::setOption([
                'fontDir' => public_path('/fonts'),
                'timeout' => 1800,
                'isPhpEnabled' => true,
                'chroot' => public_path(),
                'isRemoteEnabled' => true,
                'defaultFont' => 'Poppins',
                'dpi' => 96,
                'defaultPaperSize' => 'A4',
            ])->loadView('pdf.cash_image', compact('imageData'));

            $this->updateJobStatus('processing', 'Saving PDF file...', 75);

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = $this->totalBatches > 1
                ? "cash_images_batch_{$this->batchNumber}_of_{$this->totalBatches}_{$timestamp}.pdf"
                : "cash_images_{$timestamp}.pdf";

            $pdfPath = "pdfs/{$filename}";

            Storage::put($pdfPath, $pdf->output());

            // Clean up memory
            unset($pdf, $imageData, $data);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $this->updateJobStatus('completed', "Batch {$this->batchNumber} completed successfully!", 100, [
                'download_url' => Storage::url($pdfPath),
                'filename' => $filename,
                'file_size' => Storage::size($pdfPath),
                'total_items' => $totalItems,
                'batch_number' => $this->batchNumber,
                'total_batches' => $this->totalBatches,
                'generated_at' => now()->toISOString()
            ]);

            Log::info("Batch {$this->batchNumber} PDF generated successfully: {$filename}");

        } catch (Exception $e) {
            Log::error("PDF Generation Job Failed (Batch {$this->batchNumber}): " . $e->getMessage(), [
                'job_id' => $this->jobId,
                'batch' => $this->batchNumber,
                'trace' => $e->getTraceAsString()
            ]);

            $this->updateJobStatus('failed', $e->getMessage());
            throw $e;
        }
    }

    public function failed(Exception $exception)
    {
        Log::error("PDF Generation Job Finally Failed (Batch {$this->batchNumber}): " . $exception->getMessage(), [
            'job_id' => $this->jobId,
            'batch' => $this->batchNumber
        ]);

        $this->updateJobStatus('failed', "Batch {$this->batchNumber} failed: " . $exception->getMessage());
    }

    private function updateJobStatus($status, $message = null, $progress = null, $additionalData = [])
    {
        $statusData = array_merge([
            'status' => $status,
            'updated_at' => now(),
            'batch' => $this->batchNumber,
            'total_batches' => $this->totalBatches
        ], $additionalData);

        if ($message) {
            $statusData['message'] = $message;
        }

        if ($progress !== null) {
            $statusData['progress'] = $progress;
        }

        Cache::put("pdf_job_{$this->jobId}", $statusData, 7200);
    }
}
