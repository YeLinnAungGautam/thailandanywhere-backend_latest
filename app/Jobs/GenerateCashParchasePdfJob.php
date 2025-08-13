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
    protected $offset;
    protected $batchSize;
    protected $batchNumber;
    protected $totalBatches;

    public $timeout = 600; // 10 minutes per batch
    public $tries = 2;

    public function __construct($requestData, $jobId, $offset, $batchSize, $batchNumber, $totalBatches)
    {
        $this->requestData = $requestData;
        $this->jobId = $jobId;
        $this->offset = $offset;
        $this->batchSize = $batchSize;
        $this->batchNumber = $batchNumber;
        $this->totalBatches = $totalBatches;
    }

    public function handle(CashImageService $cashImageService)
    {
        try {
            Log::info("Processing batch {$this->batchNumber}/{$this->totalBatches} for job {$this->jobId}");

            // Memory နဲ့ time limit
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 600);

            // Request object
            $request = new Request($this->requestData);

            // Current batch data ယူမယ်

            $data = $cashImageService->getAllPurchaseForPrintBatch($request, $this->offset, $this->batchSize);

            // Check if we have data - handle both old and new response formats
            $imageData = null;
            if (isset($data['result']['data']) && !empty($data['result']['data'])) {
                $imageData = $data['result']['data'];
            } elseif (isset($data['data']) && !empty($data['data'])) {
                $imageData = $data['data'];
            } elseif (is_array($data) && !empty($data)) {
                $imageData = $data;
            }


            if (empty($data['result']['data'])) {
                $this->markBatchComplete(null);
                return;
            }

            $imageData = $data['result']['data'];
            $itemCount = count($imageData);

            Log::info("Generating PDF for batch {$this->batchNumber} with {$itemCount} items");

            // PDF generate
            $pdf = Pdf::setOption([
                'fontDir' => public_path('/fonts'),
                'timeout' => 600,
                'isPhpEnabled' => true,
                'chroot' => public_path(),
                'isRemoteEnabled' => true,
                'defaultFont' => 'Poppins',
                'dpi' => 96,
                'defaultPaperSize' => 'A4',
            ])->loadView('pdf.cash_parchase', compact('imageData'));

            // File name
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "cash_purchase_batch_{$this->batchNumber}_{$timestamp}.pdf";
            $pdfPath = "pdfs/batches/{$filename}";

            // PDF save
            Storage::put($pdfPath, $pdf->output());

            $fileInfo = [
                'batch_number' => $this->batchNumber,
                'filename' => $filename,
                'path' => $pdfPath,
                'download_url' => Storage::url($pdfPath),
                'size' => Storage::size($pdfPath),
                'item_count' => $itemCount,
                'generated_at' => now()->toISOString()
            ];

            $this->markBatchComplete($fileInfo);

            // အောက်တစ်ခု ရှိရင် dispatch လုပ်မယ်
            if ($this->batchNumber < $this->totalBatches) {
                $this->dispatchNextBatchIfNeeded();
            }

            Log::info("Batch {$this->batchNumber} completed: {$filename}");

            // Memory cleanup
            unset($imageData, $data, $pdf);
            gc_collect_cycles();

        } catch (Exception $e) {
            Log::error("Batch PDF Generation Failed (Batch {$this->batchNumber}): " . $e->getMessage());
            $this->markBatchFailed($e->getMessage());
            throw $e;
        }
    }

    private function markBatchComplete($fileInfo)
    {
        $jobStatus = Cache::get("batch_pdf_job_{$this->jobId}");

        if (!$jobStatus) {
            Log::warning("Job status not found for {$this->jobId}");
            return;
        }

        $jobStatus['completed_batches']++;

        if ($fileInfo) {
            $jobStatus['batch_files'][] = $fileInfo;
        }

        $jobStatus['progress'] = ($jobStatus['completed_batches'] / $jobStatus['total_batches']) * 100;
        $jobStatus['updated_at'] = now();

        // အားလုံး ပြီးရင် status ကို completed ပြောင်း
        if ($jobStatus['completed_batches'] >= $jobStatus['total_batches']) {
            $jobStatus['status'] = 'completed';
            $jobStatus['message'] = 'All batches completed successfully!';
        }

        Cache::put("batch_pdf_job_{$this->jobId}", $jobStatus, 7200);
    }

    private function markBatchFailed($error)
    {
        $jobStatus = Cache::get("batch_pdf_job_{$this->jobId}");

        if ($jobStatus) {
            $jobStatus['status'] = 'failed';
            $jobStatus['message'] = "Batch {$this->batchNumber} failed: {$error}";
            $jobStatus['failed_batch'] = $this->batchNumber;
            $jobStatus['updated_at'] = now();

            Cache::put("batch_pdf_job_{$this->jobId}", $jobStatus, 7200);
        }
    }

    private function dispatchNextBatchIfNeeded()
    {
        // အောက်တစ်ခု ရှိရင် dispatch လုပ်မယ်
        if ($this->batchNumber < $this->totalBatches) {
            $nextBatchNumber = $this->batchNumber + 1;
            $nextOffset = $this->offset + $this->batchSize;

            // 5 စက္ကန့် delay ထားမယ် (server load လျှော့ဖို့)
            GenerateCashParchasePdfJob::dispatch(
                $this->requestData,
                $this->jobId,
                $nextOffset,
                $this->batchSize,
                $nextBatchNumber,
                $this->totalBatches
            )->onQueue('pdf_generation')->delay(now()->addSeconds(5));
        }
    }

    public function failed(Exception $exception)
    {
        Log::error("Batch PDF Job Finally Failed (Batch {$this->batchNumber}): " . $exception->getMessage());
        $this->markBatchFailed('Batch failed after multiple attempts: ' . $exception->getMessage());
    }
}
