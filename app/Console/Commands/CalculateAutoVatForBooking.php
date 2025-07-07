<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class CalculateAutoVatForBooking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:auto-calculate-vat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Booking of auto-calculate-vat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting VAT calculation for bookings...');

        try{
            $query = Booking::query();

            $query->whereNotNull('grand_total');

            $bookings = $query->get();

            if($bookings->isEmpty()){
                $this->error('❌ No bookings found for VAT calculation.');
                return 1;
            }

            foreach ($bookings as $booking) {
                $grandTotal = (float) $booking->grand_total;
                $outputVat = $grandTotal * 0.07;
                $booking->update(['output_vat' => $outputVat]);
            }
        }catch (\Exception $e) {
            $this->error('❌ Failed to calculate VAT: ' . $e->getMessage());
            return 1;
        }

        $this->info('VAT calculation completed successfully!');
    }
}
