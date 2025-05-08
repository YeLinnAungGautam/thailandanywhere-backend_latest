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
    protected $signature = 'app:clean-invalid-strings';

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
        $tables = ['users', 'posts', 'comments']; // Add your tables here
        $invalidValues = ['undefined', 'null', ''];

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);

            foreach ($columns as $column) {
                DB::table($table)
                    ->whereIn($column, $invalidValues)
                    ->update([$column => null]);
            }
        }

        $this->info('Cleaned invalid strings from tables.');
    }
}
