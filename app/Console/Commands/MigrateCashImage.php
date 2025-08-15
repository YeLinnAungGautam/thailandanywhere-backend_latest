<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingItemGroup;
use App\Models\CashBook;
use App\Models\CashImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCashImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:cash-image {--dry-run : Show what would be migrated without actually doing it} {--debug : Show detailed information about what\'s happening} {--force : Migrate even if related models don\'t exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate cash images from relatable columns to cash_imageables pivot table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $debug = $this->option('debug');

        if ($debug) {
            $this->debugAnalysis();

            return;
        }

        CashImage::whereNotNull('relatable_type')
            ->whereNotNull('relatable_id')
            ->chunk(100, function ($cashImages) use ($isDryRun) {
                foreach ($cashImages as $cashImage) {
                    $this->migrateCashImage($cashImage, $isDryRun, $this->option('force'));
                }
            });
    }

    /**
     * Debug analysis to find missing records
     */
    private function debugAnalysis()
    {
        $this->info('🔍 Analyzing missing records...');

        // Count total cash images with relatable data
        $totalWithRelatable = CashImage::whereNotNull('relatable_type')
            ->whereNotNull('relatable_id')
            ->count();

        // Count migrated records
        $totalMigrated = DB::table('cash_imageables')->count();

        $this->info("Total cash images with relatable data: {$totalWithRelatable}");
        $this->info("Total migrated to cash_imageables: {$totalMigrated}");
        $this->info("Missing: " . ($totalWithRelatable - $totalMigrated));

        // Analyze by relatable type
        $this->info("\n📊 Breakdown by relatable type:");
        $typeBreakdown = CashImage::whereNotNull('relatable_type')
            ->whereNotNull('relatable_id')
            ->select('relatable_type', DB::raw('count(*) as count'))
            ->groupBy('relatable_type')
            ->get();

        foreach ($typeBreakdown as $type) {
            $migrated = DB::table('cash_imageables')
                ->where('imageable_type', $type->relatable_type)
                ->count();

            $this->info("  {$type->relatable_type}: {$type->count} total, {$migrated} migrated, " . ($type->count - $migrated) . " missing");
        }

        // Find specific issues
        $this->info("\n🔍 Analyzing specific issues:");

        $unknownTypes = 0;
        $missingModels = 0;
        $alreadyMigrated = 0;

        CashImage::whereNotNull('relatable_type')
            ->whereNotNull('relatable_id')
            ->chunk(100, function ($cashImages) use (&$unknownTypes, &$missingModels, &$alreadyMigrated) {
                foreach ($cashImages as $cashImage) {
                    $imageableType = $this->mapRelatableToImageableType($cashImage->relatable_type);

                    if (!$imageableType) {
                        $unknownTypes++;

                        continue;
                    }

                    $relatedModel = $this->findRelatedModel($cashImage->relatable_type, $cashImage->relatable_id);
                    if (!$relatedModel) {
                        $missingModels++;

                        continue;
                    }

                    $existingRelation = DB::table('cash_imageables')
                        ->where('cash_image_id', $cashImage->id)
                        ->where('imageable_type', $imageableType)
                        ->where('imageable_id', $cashImage->relatable_id)
                        ->exists();

                    if ($existingRelation) {
                        $alreadyMigrated++;
                    }
                }
            });

        $this->info("Unknown relatable types: {$unknownTypes}");
        $this->info("Missing related models: {$missingModels}");
        $this->info("Already migrated: {$alreadyMigrated}");

        // Show unknown types if any
        if ($unknownTypes > 0) {
            $this->info("\n❓ Unknown relatable types:");
            $unknownTypesList = CashImage::whereNotNull('relatable_type')
                ->whereNotNull('relatable_id')
                ->select('relatable_type', DB::raw('count(*) as count'))
                ->groupBy('relatable_type')
                ->get()
                ->filter(function ($type) {
                    return !$this->mapRelatableToImageableType($type->relatable_type);
                });

            foreach ($unknownTypesList as $type) {
                $this->info("  {$type->relatable_type}: {$type->count} records");
            }
        }

        // Show examples of missing models
        if ($missingModels > 0) {
            $this->info("\n🔍 Examples of missing related models:");
            $count = 0;
            CashImage::whereNotNull('relatable_type')
                ->whereNotNull('relatable_id')
                ->chunk(100, function ($cashImages) use (&$count) {
                    foreach ($cashImages as $cashImage) {
                        if ($count >= 10) {
                            return false;
                        } // Show only first 10 examples

                        $imageableType = $this->mapRelatableToImageableType($cashImage->relatable_type);
                        if (!$imageableType) {
                            continue;
                        }

                        $relatedModel = $this->findRelatedModel($cashImage->relatable_type, $cashImage->relatable_id);
                        if (!$relatedModel) {
                            $this->info("  CashImage#{$cashImage->id} -> {$cashImage->relatable_type}#{$cashImage->relatable_id} (model not found)");
                            $count++;
                        }
                    }
                });
        }
    }

    /**
     * Migrate a single cash image to the cash_imageables table
     */
    private function migrateCashImage(CashImage $cashImage, bool $isDryRun, bool $force = false): void
    {
        $relatableType = $cashImage->relatable_type;
        $relatableId = $cashImage->relatable_id;

        $imageableType = $this->mapRelatableToImageableType($relatableType);

        if (!$imageableType) {
            return;
        }

        // Only check if related model exists if not forcing
        if (!$force) {
            $relatedModel = $this->findRelatedModel($relatableType, $relatableId);
            if (!$relatedModel) {
                return;
            }
        }

        $existingRelation = DB::table('cash_imageables')
            ->where('cash_image_id', $cashImage->id)
            ->where('imageable_type', $imageableType)
            ->where('imageable_id', $relatableId)
            ->exists();

        if ($existingRelation) {
            return;
        }

        if ($isDryRun) {
            return;
        }

        DB::table('cash_imageables')->insert([
            'cash_image_id' => $cashImage->id,
            'imageable_type' => $imageableType,
            'imageable_id' => $relatableId,
            'type' => null,
            'deposit' => null,
            'notes' => null,
            'created_at' => $cashImage->created_at,
            'updated_at' => $cashImage->updated_at,
        ]);
    }

    /**
     * Map relatable type to imageable type
     */
    private function mapRelatableToImageableType(string $relatableType): ?string
    {
        return match ($relatableType) {
            'App\\Models\\Booking' => 'App\\Models\\Booking',
            'App\\Models\\BookingItemGroup' => 'App\\Models\\BookingItemGroup',
            'App\\Models\\CashBook' => 'App\\Models\\CashBook',
            default => null,
        };
    }

    /**
     * Find the related model to verify it exists
     */
    private function findRelatedModel(string $relatableType, int $relatableId)
    {
        return match ($relatableType) {
            'App\\Models\\Booking' => Booking::find($relatableId),
            'App\\Models\\BookingItemGroup' => BookingItemGroup::find($relatableId),
            'App\\Models\\CashBook' => CashBook::find($relatableId),
            default => null,
        };
    }
}
