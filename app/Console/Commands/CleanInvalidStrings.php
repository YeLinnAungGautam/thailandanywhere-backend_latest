<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanInvalidStrings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:invalid-strings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    // public function handle()
    // {
    //     $tables = ['bookings'];
    //     $invalidValues = ['undefined', 'null', '', 'NaN'];

    //     foreach ($tables as $table) {
    //         $columns = Schema::getColumnListing($table);
    //         $doctrineColumns = Schema::getConnection()->getDoctrineSchemaManager()->listTableColumns($table);

    //         foreach ($columns as $column) {
    //             $columnType = $doctrineColumns[$column]->getType()->getName();
    //             $isNullable = !$doctrineColumns[$column]->getNotnull();

    //             // Only clean columns that are nullable and either string, text, or similar
    //             if ($isNullable && in_array($columnType, ['string', 'text', 'json', 'guid'])) {
    //                 DB::table($table)
    //                     ->whereIn($column, $invalidValues)
    //                     ->update([$column => null]);

    //                 $this->info("Cleaned `$column` column in `$table`.");
    //             }
    //         }
    //     }

    //     $this->info('✅ Done cleaning invalid string values.');
    // }

    // public function handle()
    // {
    //     $tables = ['bookings'];
    //     $invalidValues = ['undefined', 'null', '', 'NaN'];

    //     foreach ($tables as $table) {
    //         $columns = Schema::getColumnListing($table);

    //         foreach ($columns as $column) {
    //             DB::table($table)
    //                 ->whereIn($column, $invalidValues)
    //                 ->update([$column => null]);
    //         }
    //     }

    //     $this->info('Cleaned invalid strings from tables.');
    // }

    public function handle()
    {
        $tables = [
            'bookings',
            'booking_items',
            'hotels',
            'rooms',
            'entrance_tickets',
            'entrance_ticket_variations',
            'private_van_tours',
            'airport_pickup_cars',
        ];

        $invalidValues = [
            'undefined',
            'null',
            '',
            'NaN',
            '<p>null</p>',
            '[{"mm_link":null,"en_link":null}]',
            '[{"info":null,"child_price":null,"child_owner_price":null,"child_cost_price":null,"child_agent_price":null}]'
        ];

        foreach ($tables as $table) {
            $columnsInfo = DB::select("
                SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
                FROM information_schema.COLUMNS
                WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()
            ", [$table]);

            foreach ($columnsInfo as $column) {
                $columnName = $column->COLUMN_NAME;
                $dataType = $column->DATA_TYPE;
                $isNullable = $column->IS_NULLABLE === 'YES';

                // Only process string-like and nullable columns
                if (in_array($dataType, ['varchar', 'text', 'char', 'mediumtext', 'longtext'])) {
                    DB::table($table)
                        ->whereIn($columnName, $invalidValues)
                        ->update([$columnName => null]);

                    $this->info("✅ Updated `$columnName` in `$table`.");
                }
            }
        }

        $this->info('🎉 Done cleaning string columns without Doctrine.');
    }
}
