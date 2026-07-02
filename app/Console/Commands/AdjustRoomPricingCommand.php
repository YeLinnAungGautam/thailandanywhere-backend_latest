<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdjustRoomPricingCommand extends Command
{
    /**
     * php artisan rooms:adjust-pricing
     * php artisan rooms:adjust-pricing --dry-run
     * php artisan rooms:adjust-pricing --threshold=1300 --multiplier=1.12 --min-margin=0.10
     */
    protected $signature = 'rooms:adjust-pricing
        {--dry-run : Preview changes without writing to the database}
        {--threshold=1300 : Only consider rooms at or below this room_price}
        {--multiplier=1.12 : Multiply cost by this factor to get the new room_price}
        {--min-margin=0.10 : Skip rooms whose current margin is below this}';

    protected $description = 'Repricing pass: set room_price = cost x multiplier for eligible rooms (<= threshold THB, excluding extras/breakfast rooms/thin-margin rooms)';

    public function handle(): int
    {
        $isDryRun    = (bool) $this->option('dry-run');
        $threshold   = (float) $this->option('threshold');
        $multiplier  = (float) $this->option('multiplier');
        $minMargin   = (float) $this->option('min-margin');

        $this->info(sprintf(
            '%sRunning repricing pass | threshold=%.2f THB | multiplier=%.2f | min-margin=%.2f',
            $isDryRun ? '[DRY RUN] ' : '',
            $threshold,
            $multiplier,
            $minMargin
        ));

        // Base filter: price <= threshold, not an extra/add-on room, no breakfast included,
        // and room_price must be > 0 so margin is computable.
        $rooms = Room::query()
            ->where('room_price', '<=', $threshold)
            ->where('room_price', '>', 0)
            ->where('cost', '>', 0)
            ->where('is_extra', 0)
            ->where(function ($q) {
                $q->whereNull('has_breakfast')->orWhere('has_breakfast', 0);
            })
            ->get();

        $eligible = [];
        $skippedMargin = 0;
        $skippedNoChange = 0;

        foreach ($rooms as $room) {
            $margin = ($room->room_price - $room->cost) / $room->room_price;

            if ($margin < $minMargin) {
                $skippedMargin++;
                continue;
            }

            $newPrice = round($room->cost * $multiplier, 2);

            if ((float) $newPrice === (float) $room->room_price) {
                $skippedNoChange++;
                continue;
            }

            $eligible[] = [
                'room'      => $room,
                'old_price' => $room->room_price,
                'new_price' => $newPrice,
                'margin'    => round($margin, 3),
            ];
        }

        if (empty($eligible)) {
            $this->warn('No rooms matched the criteria (or nothing needed updating).');
            $this->line("Skipped for margin < {$minMargin}: {$skippedMargin}");
            $this->line("Skipped (price already correct): {$skippedNoChange}");
            return self::SUCCESS;
        }

        $this->table(
            ['Room ID', 'Name', 'Cost', 'Old Price', 'New Price', 'Old Margin'],
            collect($eligible)->map(fn ($r) => [
                $r['room']->id,
                $r['room']->name,
                $r['room']->cost,
                $r['old_price'],
                $r['new_price'],
                $r['margin'],
            ])
        );

        if ($isDryRun) {
            $this->info(count($eligible) . ' room(s) would be updated. No changes written (dry run).');
            return self::SUCCESS;
        }

        if (!$this->confirm(count($eligible) . ' room(s) will be updated. Continue?', true)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        DB::beginTransaction();

        try {
            foreach ($eligible as $r) {
                $r['room']->update(['room_price' => $r['new_price']]);
            }

            DB::commit();

            $this->info(count($eligible) . ' room(s) updated successfully.');
            Log::info('rooms:adjust-pricing completed', [
                'updated_count' => count($eligible),
                'threshold'     => $threshold,
                'multiplier'    => $multiplier,
                'min_margin'    => $minMargin,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed: ' . $e->getMessage());
            Log::error($e);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
