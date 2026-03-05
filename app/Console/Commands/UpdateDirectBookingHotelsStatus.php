<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use Illuminate\Console\Command;

class UpdateDirectBookingHotelsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hotels:update-direct-booking-status
    {--dry-run : Preview affected hotels without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set data_status to "contract_active" for all hotels where type is "direct_booking"';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $query = Hotel::where('type', Hotel::TYPES['direct_booking']);

        $count = $query->count();

        if ($count === 0) {
            $this->warn('No hotels found with type "direct_booking".');
            return self::SUCCESS;
        }

        $this->info("Found {$count} hotel(s) with type = 'direct_booking'.");

        if ($isDryRun) {
            $this->line('');
            $this->warn('[DRY RUN] No changes will be made. Affected hotels:');
            $this->line('');

            $query->select('id', 'name', 'type', 'data_status')->each(function (Hotel $hotel) {
                $this->line("  ID: {$hotel->id} | Name: {$hotel->name} | Current data_status: {$hotel->data_status}");
            });

            $this->line('');
            $this->info("Would update {$count} hotel(s) → data_status = 'contract_active'");

            return self::SUCCESS;
        }

        if (!$this->confirm("Update data_status to 'contract_active' for {$count} hotel(s)?", true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $updated = Hotel::where('type', Hotel::TYPES['direct_booking'])
            ->update(['data_status' => 'contract_active']);

        $this->info("✅ Successfully updated {$updated} hotel(s) → data_status = 'contract_active'");

        return self::SUCCESS;
    }
}
