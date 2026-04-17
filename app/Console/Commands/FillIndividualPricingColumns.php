<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillIndividualPricingColumns extends Command
{
    protected $signature = 'pricing:fill-columns
                            {--table=all : Table to process (all, order_items, booking_items)}
                            {--dry-run : Preview changes without saving}
                            {--chunk=100 : Number of records per chunk}';

    protected $description = 'Fill child/adult/infant pricing columns from individual_pricing JSON field';

    // Pricing types to extract
    private array $pricingTypes = ['child', 'adult', 'infant'];

    // Map from JSON keys to column names
    private array $fieldMap = [
        'quantity'        => 'quantity',
        'selling_price'   => 'price',
        'cost_price'      => 'cost',
        'amount'          => 'total_selling_price',
        'total_cost_price'=> 'total_cost',
    ];

    public function handle(): int
    {
        $table   = $this->option('table');
        $dryRun  = $this->option('dry-run');
        $chunk   = (int) $this->option('chunk');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN — no data will be written.');
        }

        $tables = match ($table) {
            'order_items'   => ['order_items'],
            'booking_items' => ['booking_items'],
            default         => ['order_items', 'booking_items'],
        };

        foreach ($tables as $tbl) {
            $this->processTable($tbl, $dryRun, $chunk);
        }

        $this->info('✅ Done.');
        return self::SUCCESS;
    }

    private function processTable(string $table, bool $dryRun, int $chunk): void
    {
        $this->info("\n📋 Processing table: <fg=cyan>{$table}</>");

        $total   = DB::table($table)->whereNotNull('individual_pricing')->count();
        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        if ($total === 0) {
            $this->warn("  No rows with individual_pricing found. Skipping.");
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::table($table)
            ->whereNotNull('individual_pricing')
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use ($table, $dryRun, $bar, &$updated, &$skipped, &$errors) {
                foreach ($rows as $row) {
                    $bar->advance();

                    $decoded = $this->decodeJson($row->individual_pricing);

                    if ($decoded === null) {
                        $errors++;
                        $this->newLine();
                        $this->warn("  ⚠ Row ID {$row->id}: invalid JSON — skipped.");
                        continue;
                    }

                    if (empty($decoded)) {
                        $skipped++;
                        continue;
                    }

                    $data = $this->buildColumnData($decoded);

                    if (empty($data)) {
                        $skipped++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->newLine();
                        $this->line("  [DRY-RUN] ID {$row->id}: " . json_encode($data));
                    } else {
                        DB::table($table)->where('id', $row->id)->update($data);
                    }

                    $updated++;
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total rows found',  $total],
                ['Rows updated',      $updated],
                ['Rows skipped',      $skipped],
                ['Rows with errors',  $errors],
            ]
        );
    }

    /**
     * Decode JSON safely, handling both string and already-decoded values.
     */
    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Handle double-encoded JSON (stored as a JSON string wrapping another JSON string)
        // e.g. ""{\"child\":{...}}"" → decode again to get the actual array
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $decoded;
    }

    /**
     * Build the flat column => value array from the nested pricing JSON.
     *
     * JSON structure (either table):
     * {
     *   "child": {
     *     "quantity": "2",
     *     "selling_price": "675",
     *     "cost_price": "575",
     *     "amount": 1350,          <- total selling
     *     "total_cost_price": 1150 <- total cost
     *   },
     *   "adult": { ... },
     *   "infant": { ... }
     * }
     */
    private function buildColumnData(array $pricing): array
    {
        $data = [];

        foreach ($this->pricingTypes as $type) {
            if (!isset($pricing[$type]) || !is_array($pricing[$type])) {
                continue;
            }

            $values = $pricing[$type];

            foreach ($this->fieldMap as $jsonKey => $columnSuffix) {
                if (!array_key_exists($jsonKey, $values)) {
                    continue;
                }

                $column         = "{$type}_{$columnSuffix}";   // e.g. child_price
                $raw            = $values[$jsonKey];
                $data[$column]  = is_numeric($raw) ? (float) $raw : null;
            }
        }

        return $data;
    }
}
