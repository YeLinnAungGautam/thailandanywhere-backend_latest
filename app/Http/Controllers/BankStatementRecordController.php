<?php

namespace App\Http\Controllers;

use App\Http\Resources\BankStatementRecordResource;
use App\Models\BankStatementRecord;
use App\Models\CashImage;
use App\Services\BankStatementImportService;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BankStatementRecordController extends Controller
{
    use HttpResponses;

    public function __construct(
        protected BankStatementImportService $importService
    ) {}

    // ──────────────────────────────────────────────
    // POST /bank-statements/import
    // ──────────────────────────────────────────────
    public function import(Request $request)
    {
        $request->validate([
            'file'  => 'required|file|mimes:csv,txt|max:10240',
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        try {
            $result = $this->importService->import(
                $request->file('file'),
                (int) $request->month,
                (int) $request->year
            );
            return $this->success($result, 'Bank statement imported successfully');
        } catch (\Throwable $e) {
            Log::error('Bank statement import error: ' . $e->getMessage());
            return $this->error(null, 'Import failed: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────
    // GET /bank-statements?month=5&year=2026
    // ──────────────────────────────────────────────
    public function index(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020',
        ]);

        $records = BankStatementRecord::with('cashImage:id,sender,receiver,amount,date,bank_verify')
            ->where('month', $request->month)
            ->where('year',  $request->year)

            ->when($request->verified, function ($query) use ($request) {
                $query->where('verified', $request->verified);
            })
            ->orderBy('txn_date', 'asc')
            // ->orderBy('desc')
            ->paginate($request->get('limit', 50));

        return $this->success(BankStatementRecordResource::collection($records)->additional([
            'meta' => [
                'total_page' => (int) ceil($records->total() / $records->perPage()),
            ],
        ])
        ->response()
        ->getData(), 'Bank statement records retrieved');
    }

    // POST /bank-statements/rematch?month=5&year=2026
    public function rematch(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020',
        ]);

        try {
            $counts = $this->importService->rematch(
                (int) $request->month,
                (int) $request->year
            );
            return $this->success($counts, 'Re-match completed successfully');
        } catch (\Throwable $e) {
            Log::error('Re-match error: ' . $e->getMessage());
            return $this->error(null, 'Re-match failed: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────
    // GET /bank-statements/summary?month=5&year=2026
    // ──────────────────────────────────────────────
    public function summary(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020',
        ]);

        $base = BankStatementRecord::where('month', $request->month)
                                   ->where('year',  $request->year);

        $summary = [
            'total'            => (clone $base)->count(),
            'match'            => (clone $base)->where('verified', 'match')->count(),
            'duplicate'        => (clone $base)->where('verified', 'duplicate')->count(),
            'unmatch'          => (clone $base)->where('verified', 'unmatch')->count(),
            'total_withdrawal' => (clone $base)->sum('withdrawal'),
            'total_deposit'    => (clone $base)->sum('deposit'),
        ];

        return $this->success($summary, 'Summary retrieved');
    }

    // ──────────────────────────────────────────────
    // GET /bank-statements/{id}/duplicates
    // Return the candidate CashImages stored in duplicate_ids
    // so the user can pick the correct one.
    // ──────────────────────────────────────────────
    public function duplicateCandidates(int $id)
    {
        $record = BankStatementRecord::findOrFail($id);

        if ($record->verified !== 'duplicate' || !$record->duplicate_ids) {
            return $this->error(null, 'This record has no duplicate candidates.', 422);
        }

        $ids        = explode(',', $record->duplicate_ids);
        $cashImages = CashImage::whereIn('id', $ids)
            ->select('id', 'sender', 'receiver', 'amount', 'date', 'currency', 'interact_bank', 'bank_verify')
            ->get();

        return $this->success([
            'statement_record' => $record,
            'candidates'       => $cashImages,
        ], 'Duplicate candidates retrieved');
    }

    // ──────────────────────────────────────────────
    // POST /bank-statements/{id}/resolve
    // User picks which cash_image_id to link.
    // This sets bank_verify = true on that CashImage.
    // ──────────────────────────────────────────────
    public function resolve(Request $request, int $id)
    {
        $request->validate([
            'cash_image_id' => 'required|integer|exists:cash_images,id',
        ]);

        $record = BankStatementRecord::findOrFail($id);

        // Make sure the chosen id is actually one of the candidates
        // (or a direct match row — allow re-assignment too)
        $allowedIds = $record->duplicate_ids
            ? explode(',', $record->duplicate_ids)
            : ($record->cash_image_id ? [$record->cash_image_id] : []);

        if (!in_array((string) $request->cash_image_id, array_map('strval', $allowedIds))) {
            return $this->error(null, 'The selected cash image is not a valid candidate for this record.', 422);
        }

        // Reset bank_verify on any previously linked image for this record
        if ($record->cash_image_id) {
            CashImage::where('id', $record->cash_image_id)
                     ->update(['bank_verify' => false]);
        }

        // Link & verify
        $record->update([
            'cash_image_id' => $request->cash_image_id,
            'verified'      => 'match',
        ]);

        CashImage::where('id', $request->cash_image_id)
                 ->update(['bank_verify' => true]);

        return $this->success([
            'statement_record_id' => $record->id,
            'cash_image_id'       => $request->cash_image_id,
        ], 'Record resolved and cash image bank-verified');
    }

    // ──────────────────────────────────────────────
    // POST /bank-statements/{id}/bank-verify
    // Directly bank-verify a 'match' row (no duplicate picking needed).
    // ──────────────────────────────────────────────
    public function bankVerify(int $id)
    {
        $record = BankStatementRecord::findOrFail($id);

        if ($record->verified !== 'match' || !$record->cash_image_id) {
            return $this->error(null, 'Only matched records can be bank-verified directly.', 422);
        }

        CashImage::where('id', $record->cash_image_id)
                 ->update(['bank_verify' => true]);

        return $this->success([
            'statement_record_id' => $record->id,
            'cash_image_id'       => $record->cash_image_id,
        ], 'Cash image bank-verified');
    }

    // ──────────────────────────────────────────────
    // POST /bank-statements/bank-verify-all
    // Set bank_verify = true for all cash images linked to 'match' records in a given month/year
    // ──────────────────────────────────────────────
    public function bankVerifyAll(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020',
        ]);

        // Get all verified 'match' records with cash_image_id not null for the given month/year
        $records = BankStatementRecord::where('month', $request->month)
            ->where('year', $request->year)
            ->where('verified', 'match')
            ->whereNotNull('cash_image_id')
            ->get();

        if ($records->isEmpty()) {
            return $this->error(null, 'No matched records found for the specified month and year.', 422);
        }

        // Extract unique cash_image_ids
        $cashImageIds = $records->pluck('cash_image_id')->unique()->toArray();

        if (empty($cashImageIds)) {
            return $this->error(null, 'No cash images found to verify.', 422);
        }

        // Update all cash images to bank_verify = true
        $updatedCount = CashImage::whereIn('id', $cashImageIds)
            ->update(['bank_verify' => true]);

        return $this->success([
            'total_records'     => $records->count(),
            'unique_cash_images'=> count($cashImageIds),
            'updated_count'     => $updatedCount,
            'month'             => $request->month,
            'year'              => $request->year,
        ], "Successfully bank-verified {$updatedCount} cash images for the specified period.");
    }
}
