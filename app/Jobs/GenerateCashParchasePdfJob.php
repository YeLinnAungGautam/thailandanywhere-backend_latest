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

class GenerateCashParchasePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestData;
    protected $jobId;

    // Job configuration
    public $timeout = 1800; // 30 minutes
    public $maxExceptions = 3;
    public $tries = 3;

    public function __construct($requestData, $jobId)
    {
        $this->requestData = $requestData;
        $this->jobId = $jobId;

        // Set initial status
        Cache::put("pdf_job_{$this->jobId}", [
            'status' => 'queued',
            'created_at' => now(),
            'progress' => 0
        ], 3600); // 1 hour cache
    }

    public function handle(CashImageService $cashImageService)
    {
        try {
            // Update status to processing
            $this->updateJobStatus('processing', null, 10);

            // Create request object from array
            $request = new Request($this->requestData);

            // Set PHP limits for large datasets
            ini_set('memory_limit', '1024M'); // 1GB
            ini_set('max_execution_time', 1800); // 30 minutes
            set_time_limit(1800);

            $this->updateJobStatus('processing', 'Fetching data from database...', 25);

            // Get all data using your existing service
            $data = $cashImageService->getAllParchaseForPrint($request);

            if (empty($data['result']['data'])) {
                $this->updateJobStatus('failed', 'No data found to generate PDF');
                return;
            }

            $this->updateJobStatus('processing', 'Processing invoice data...', 50);

            $imageData = $data['result']['data'];
            $totalItems = count($imageData);

            // Log for debugging
            Log::info("Generating PDF for {$totalItems} items");

            $this->updateJobStatus('processing', "Generating PDF for {$totalItems} items...", 75);

            // Generate PDF using your existing logic
            $pdf = Pdf::setOption([
                'fontDir' => public_path('/fonts'),
                'timeout' => 1800,
                'isPhpEnabled' => true,
                'chroot' => public_path(),
                'isRemoteEnabled' => true,
                'defaultFont' => 'Poppins',
                'dpi' => 96,
                'defaultPaperSize' => 'A4',
            ])->loadView('pdf.cash_parchase', compact('imageData'));

            $this->updateJobStatus('processing', 'Saving PDF file...', 90);

            // Generate filename with timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "cash_images_{$timestamp}.pdf";
            $pdfPath = "pdfs/{$filename}";

            // Save PDF to storage
            Storage::put($pdfPath, $pdf->output());

            // Update status to completed
            $this->updateJobStatus('completed', 'PDF generated successfully!', 100, [
                'download_url' => Storage::url($pdfPath),
                'filename' => $filename,
                'file_size' => Storage::size($pdfPath),
                'total_items' => $totalItems,
                'generated_at' => now()->toISOString()
            ]);

            Log::info("PDF generated successfully: {$filename}");

        } catch (Exception $e) {
            Log::error("PDF Generation Job Failed: " . $e->getMessage(), [
                'job_id' => $this->jobId,
                'trace' => $e->getTraceAsString()
            ]);

            $this->updateJobStatus('failed', $e->getMessage());

            // Re-throw to trigger job retry if applicable
            throw $e;
        }
    }

    public function failed(Exception $exception)
    {
        Log::error("PDF Generation Job Finally Failed: " . $exception->getMessage(), [
            'job_id' => $this->jobId
        ]);

        $this->updateJobStatus('failed', 'PDF generation failed after multiple attempts: ' . $exception->getMessage());
    }

    private function updateJobStatus($status, $message = null, $progress = null, $additionalData = [])
    {
        $statusData = array_merge([
            'status' => $status,
            'updated_at' => now(),
        ], $additionalData);

        if ($message) {
            $statusData['message'] = $message;
        }

        if ($progress !== null) {
            $statusData['progress'] = $progress;
        }

        Cache::put("pdf_job_{$this->jobId}", $statusData, 3600);
    }
}
