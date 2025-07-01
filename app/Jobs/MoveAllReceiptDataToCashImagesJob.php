<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MoveAllReceiptDataToCashImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    protected int $chunkSize = 100;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::beginTransaction();

            $bookingReceiptsCount = $this->moveBookingReceiptsData();
            $expenseReceiptsCount = $this->moveReservationExpenseReceiptData();

            DB::commit();

        } catch (QueryException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Move booking_receipts data to cash_images table
     *
     * @return int Number of records migrated
     */
    private function moveBookingReceiptsData(): int
    {
        $migratedCount = 0;

        DB::table('booking_receipts')->orderBy('id')->chunk($this->chunkSize, function ($receipts) use (&$migratedCount) {
            $records = $receipts->map(function ($receipt) {
                return [
                    'id' => $receipt->id,
                    'image' => $receipt->image,
                    'amount' => $receipt->amount,
                    'date' => $receipt->date,
                    'sender' => $receipt->sender,
                    'created_at' => $receipt->created_at,
                    'updated_at' => $receipt->updated_at,
                    'receiver' => $receipt->reciever,
                    'interact_bank' => $receipt->interact_bank,
                    'currency' => $receipt->currency,
                    'relatable_type' => 'App\Models\Booking',
                    'relatable_id' => $receipt->booking_id
                ];
            })->toArray();

            try {
                $result = DB::table('cash_images')->insertOrIgnore($records);
                $migratedCount += count($records);
            } catch (QueryException $e) {

                throw $e;
            }
        });
        return $migratedCount;
    }

    /**
     * Move reservation_expense_receipt data to cash_images table
     *
     * @return int Number of records migrated
     */
    private function moveReservationExpenseReceiptData(): int
    {
        $migratedCount = 0;

        DB::table('reservation_expense_receipts as rer')
            ->join('booking_items as bi', 'rer.booking_item_id', '=', 'bi.id')
            ->select([
                'rer.id',
                'rer.file as image',
                'rer.created_at',
                'rer.updated_at',
                'rer.amount',
                'rer.date',
                'rer.reciever',
                'rer.sender',
                'rer.interact_bank',
                'rer.currency',
                'bi.group_id as relatable_id'
            ])
            ->orderBy('rer.id')
            ->chunk($this->chunkSize, function ($receipts) use (&$migratedCount) {
                $records = $receipts->map(function ($receipt) {
                    return [
                        'id' => $receipt->id,
                        'image' => $receipt->image,
                        'created_at' => $receipt->created_at,
                        'updated_at' => $receipt->updated_at,
                        'amount' => $receipt->amount,
                        'date' => $receipt->date,
                        'receiver' => $receipt->reciever,
                        'sender' => $receipt->sender,
                        'interact_bank' => $receipt->interact_bank,
                        'currency' => $receipt->currency,
                        'relatable_type' => 'App\Models\BookingItemGroup',
                        'relatable_id' => $receipt->relatable_id
                    ];
                })->toArray();

                try {
                    $result = DB::table('cash_images')->insertOrIgnore($records);
                    $migratedCount += count($records);
                } catch (QueryException $e) {
                    throw $e;
                }
            });
        return $migratedCount;
    }

    /**
     * Set the chunk size for processing
     *
     * @param int $size
     * @return self
     */
    public function setChunkSize(int $size): self
    {
        $this->chunkSize = $size;
        return $this;
    }
}
