<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migrateCustomerPassport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->migrateCustomerPassport();
    }

    private function migrateCustomerPassport()
    {
        DB::table('reservation_customer_passports')->orderBy('id')->chunk(100, function ($passports) {
            foreach ($passports as $passport) {
                $bookingItem = BookingItem::find($passport->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                if (is_null($passport->name) && is_null($passport->passport_number) && is_null($passport->dob)) {
                    $meta = null;
                } else {
                    $meta = [
                        'name' => $passport->name,
                        'passport_number' => $passport->passport_number,
                        'dob' => $passport->dob,
                    ];
                }

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'passport',
                    'file_name' => $passport->file,
                ], [
                    'file' => $passport->file,
                    'meta' => $meta ? json_encode($meta) : null,
                ]);
            }
        });
    }
}
