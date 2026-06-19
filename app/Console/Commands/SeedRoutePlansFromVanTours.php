<?php

namespace App\Console\Commands;

use App\Models\PrivateVanTour;
use App\Models\RoutePlan;
use Illuminate\Console\Command;

class SeedRoutePlansFromVanTours extends Command
{
    protected $signature   = 'routeplan:seed-from-vantours
                                {--dry-run : Preview without saving}
                                {--force  : Re-create even if a route plan already exists}';

    protected $description = 'Auto-create a RoutePlan for every van_tour type PrivateVanTour that has full data';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force  = $this->option('force');

        $vanTours = PrivateVanTour::query()
            ->with(['destinations', 'cities', 'images'])
            ->where('type', PrivateVanTour::TYPES['van_tour'])
            ->whereNotNull('full_description_en')
            ->whereNotNull('cover_image')
            ->get();

        if ($vanTours->isEmpty()) {
            $this->warn('No matching van tours found.');
            return self::SUCCESS;
        }

        $this->info("Found {$vanTours->count()} van tour(s) to process.");
        $this->newLine();

        $created = 0;
        $skipped = 0;

        foreach ($vanTours as $vt) {
            // Skip if already linked to a route plan (unless --force)
            if (!$force && $vt->routePlans()->exists()) {
                $this->line("  <fg=yellow>SKIP</>  [{$vt->id}] {$vt->name} — already has a route plan");
                $skipped++;
                continue;
            }

            $this->line("  <fg=green>CREATE</> [{$vt->id}] {$vt->name}");
            $this->line("         description      : " . str($vt->description)->limit(60));
            $this->line("         english_desc     : " . str($vt->full_description_en)->stripTags()->limit(60));
            $this->line("         mm_desc          : " . str($vt->long_description)->stripTags()->limit(60));
            $this->line("         cover_image      : " . ($vt->cover_image ?? '—'));
            $this->line("         other_photos     : " . $vt->images->count() . ' image(s)');
            $this->line("         destination_ids  : " . $vt->destinations->pluck('id')->join(', '));
            $this->line("         city_ids         : " . $vt->cities->pluck('id')->join(', '));
            $this->newLine();

            if ($dryRun) {
                $created++;
                continue;
            }

            // Map van tour fields → route plan fields
            $routePlan = RoutePlan::create([
                'route'               => $vt->description,           // pvt description → route
                'english_description' => $vt->full_description_en,   // full_description_en
                'mm_description'      => $vt->long_description,       // long_description
                'main_cover_photo'    => $vt->cover_image,            // cover_image (filename only)
                'other_photos'        => $vt->images                  // pvt images → other_photos
                                            ->pluck('image')
                                            ->values()
                                            ->toArray(),
                'destination_ids'     => $vt->destinations->pluck('id')->values()->toArray(),
                'city_ids'            => $vt->cities->pluck('id')->values()->toArray(),
            ]);

            // Link back via the pivot
            $routePlan->privateVanTours()->sync([$vt->id]);

            $created++;
        }

        $this->newLine();
        $this->info("Done. Created: {$created} | Skipped: {$skipped}" . ($dryRun ? ' (dry run — nothing saved)' : ''));

        return self::SUCCESS;
    }
}
