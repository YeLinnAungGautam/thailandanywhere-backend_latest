<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class RegenerateCRMIDDuplication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:crm_id_duplication';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $duplicate_crm_ids = Booking::with('items')->whereIn('crm_id', function ($query) {
            $query->select('crm_id')->from('bookings')->groupBy('crm_id')->havingRaw('count(*) > 1');
        })->pluck('crm_id')->unique();

        foreach($duplicate_crm_ids as $crm_id) {
            $bookings = Booking::where('crm_id', $crm_id)->get()->splice(1);

            foreach($bookings as $booking) {
                $old_crm_id = $booking->crm_id;
                $new_crm_id = $this->generateNewCRMId($booking->crm_id);

                $booking->update(['crm_id' => $new_crm_id]);

                foreach($booking->items as $booking_item) {
                    $booking_item->crm_id = str_replace($old_crm_id, $booking->crm_id, $booking_item->crm_id);
                    $booking_item->save();
                }
            }
        }

        $this->info('Duplicated records are successfully removed');
    }

    private function generateNewCRMId($booking_crm_id)
    {
        $new_crm_id = ++$booking_crm_id;

        while (Booking::where('crm_id', $new_crm_id)->exists()) {
            ++$new_crm_id;
        }

        return $new_crm_id;
    }
}
