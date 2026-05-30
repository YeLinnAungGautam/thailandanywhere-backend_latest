<?php

namespace App\Services;

use App\Models\BankStatementRecord;
use App\Models\CashImage;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankStatementImportService
{
    // ───────────────────────────────────────────────
    // PUBLIC ENTRY POINT
    // ───────────────────────────────────────────────

    /**
     * @return array{imported:int, match:int, duplicate:int, unmatch:int, month:int, year:int, account_number:string|null}
     */
    public function import(UploadedFile $file, int $month, int $year): array
    {
        $rows          = $this->parseCsv($file);
        $accountNumber = $this->extractAccountNumber($file);

        DB::beginTransaction();
        try {
            // 1. Reset bank_verify on previously matched CashImages
            $this->resetPreviousVerifications($month, $year);

            // 2. Wipe previous import rows for this month/year
            BankStatementRecord::where('month', $month)
                               ->where('year',  $year)
                               ->delete();

            // 3. Insert new rows
            $records = $this->insertRows($rows, $month, $year, $accountNumber);

            // 4. Match against CashImages (no auto bank_verify)
            $counts = $this->matchRecords($records, $month, $year);

            DB::commit();

            return [
                'imported'       => $records->count(),
                'match'          => $counts['match'],
                'duplicate'      => $counts['duplicate'],
                'unmatch'        => $counts['unmatch'],
                'month'          => $month,
                'year'           => $year,
                'account_number' => $accountNumber,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('BankStatementImportService::import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    // ───────────────────────────────────────────────
    // CSV PARSING
    // ───────────────────────────────────────────────

    private function parseCsv(UploadedFile $file): Collection
    {
        $content = file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // strip BOM

        $lines = explode("\n", $content);
        $rows  = collect();

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if (empty(trim($line))) continue;

            $cols    = str_getcsv($line);
            $rawDate = trim($cols[1] ?? '');

            if (!preg_match('/^\d{2}-\d{2}-\d{2,4}$/', $rawDate)) continue;

            $desc = trim($cols[3] ?? '');
            if ($desc === 'ยอดยกมา') continue;

            $rows->push([
                'date'        => $rawDate,
                'time'        => trim($cols[2] ?? ''),
                'description' => $desc,
                'withdrawal'  => $this->parseAmount($cols[4] ?? ''),
                'deposit'     => $this->parseAmount($cols[6] ?? ''),
                'balance'     => $this->parseAmount($cols[8] ?? ''),
                'channel'     => trim($cols[10] ?? ''),
                'detail'      => trim($cols[12] ?? ''),
            ]);
        }

        return $rows;
    }

    private function extractAccountNumber(UploadedFile $file): ?string
    {
        $content = file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines   = explode("\n", $content);
        $i       = 0;

        foreach ($lines as $line) {
            $cols = str_getcsv(rtrim($line, "\r"));
            foreach ($cols as $col) {
                if (preg_match('/\d{3}-\d-\d{5}-\d/', trim($col), $m)) {
                    return $m[0];
                }
            }
            if (++$i > 15) break;
        }
        return null;
    }

    // ───────────────────────────────────────────────
    // DATABASE HELPERS
    // ───────────────────────────────────────────────

    private function insertRows(Collection $rows, int $month, int $year, ?string $accountNumber): Collection
    {
        $saved = collect();

        foreach ($rows as $row) {
            $date = $this->parseDate($row['date']);
            if (!$date) continue;

            $record = BankStatementRecord::create([
                'month'          => $month,
                'year'           => $year,
                'account_number' => $accountNumber,
                'txn_date'       => $date->toDateString(),
                'txn_time'       => $row['time'] ?: null,
                'description'    => $row['description'],
                'withdrawal'     => $row['withdrawal'],
                'deposit'        => $row['deposit'],
                'balance'        => $row['balance'],
                'channel'        => $row['channel'],
                'detail'         => $row['detail'],
                'verified'       => 'unmatch', // default
            ]);

            $saved->push($record);
        }

        return $saved;
    }

    /**
     * Match each statement row against CashImages:
     *   interact_bank = 'company'  AND  data_verify = 1 (true)
     *
     * Match key: txn_date + txn_time (HH:MM) + amount
     *
     * Results:
     *   1 candidate  → cash_image_id = id,  verified = 'match'
     *   2+ candidates → duplicate_ids = '1,2,3', verified = 'duplicate'
     *   0 candidates  → verified = 'unmatch'   (already default)
     *
     * NO bank_verify is touched here — that's a manual user action.
     */
    private function matchRecords(Collection $records, int $month, int $year): array
    {
        $counts = ['match' => 0, 'duplicate' => 0, 'unmatch' => 0];

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate   = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        // Pre-load eligible CashImages once
        $candidates = CashImage::where('interact_bank', 'company')
            ->where('data_verify', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->map(function (CashImage $ci) {
                $dt = Carbon::parse($ci->date);
                return [
                    'id'             => $ci->id,
                    'date_str'       => $dt->toDateString(),   // Y-m-d
                    'time_str'       => $dt->format('H:i'),    // HH:MM
                    'amount'         => (float) $ci->amount,
                    'relatable_type' => $ci->relatable_type,
                ];
            });

        foreach ($records as $record) {
            if (!$record->txn_time) {
                $counts['unmatch']++;
                continue;
            }

            // Determine expected amount & relatable_type from statement side
            if ($record->withdrawal) {
                $amount        = (float) $record->withdrawal;
                $relatableType = 'App\\Models\\BookingItemGroup';
            } elseif ($record->deposit) {
                $amount        = (float) $record->deposit;
                $relatableType = 'App\\Models\\Booking';
            } else {
                $counts['unmatch']++;
                continue;
            }

            $txnTime = substr($record->txn_time, 0, 5); // HH:MM

            $matches = $candidates->filter(function ($ci) use ($record, $amount, $relatableType, $txnTime) {
                return $ci['date_str'] === $record->txn_date->toDateString()
                    && $ci['time_str'] === $txnTime
                    && abs($ci['amount'] - $amount) < 0.01
                    && $ci['relatable_type'] === $relatableType;
            })->values();

            if ($matches->count() === 1) {
                $record->update([
                    'cash_image_id' => $matches[0]['id'],
                    'duplicate_ids' => null,
                    'verified'      => 'match',
                ]);
                $counts['match']++;

            } elseif ($matches->count() > 1) {
                $record->update([
                    'cash_image_id' => null,
                    'duplicate_ids' => $matches->pluck('id')->implode(','),
                    'verified'      => 'duplicate',
                ]);
                $counts['duplicate']++;

            } else {
                // stays 'unmatch'
                $counts['unmatch']++;
            }
        }

        return $counts;
    }

    private function resetPreviousVerifications(int $month, int $year): void
    {
        $cashImageIds = BankStatementRecord::where('month', $month)
            ->where('year', $year)
            ->whereNotNull('cash_image_id')
            ->pluck('cash_image_id');

        if ($cashImageIds->isNotEmpty()) {
            CashImage::whereIn('id', $cashImageIds)
                     ->update(['bank_verify' => false]);
        }
    }

    // ───────────────────────────────────────────────
    // UTILITY
    // ───────────────────────────────────────────────

    private function parseAmount(string $raw): ?float
    {
        $clean = str_replace(',', '', trim($raw));
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function parseDate(string $raw): ?Carbon
    {
        try {
            [$d, $m, $y] = explode('-', $raw);
            $fullYear = (int)$y < 100 ? 2000 + (int)$y : (int)$y;
            return Carbon::create($fullYear, (int)$m, (int)$d);
        } catch (\Throwable) {
            return null;
        }
    }
}
