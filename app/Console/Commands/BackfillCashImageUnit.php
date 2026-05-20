<?php

namespace App\Console\Commands;

use App\Models\CashImage;
use Illuminate\Console\Command;

class BackfillCashImageUnit extends Command
{
    protected $signature = 'cash-image:backfill-unit';
    protected $description = 'Generate and assign unit codes to all CashImage records that are missing one';

    public function handle(): void
    {
        $records = CashImage::whereNull('unit')->orWhere('unit', '')->get();

        if ($records->isEmpty()) {
            $this->info('All records already have a unit code.');
            return;
        }

        $this->info("Found {$records->count()} records to update...");
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        foreach ($records as $cashImage) {
            $cashImage->update(['unit' => CashImage::generateUnit()]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done! All cash images now have a unit code.');
    }
}
