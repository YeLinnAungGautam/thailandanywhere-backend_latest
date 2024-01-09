<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_inclusive')->default(0);
            $table->string('inclusive_name')->nullable();
            $table->tinyInteger('inclusive_quantity')->nullable();
            $table->bigInteger('inclusive_rate')->nullable();
            $table->date('inclusive_start_date')->nullable();
            $table->date('inclusive_end_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'is_inclusive',
                'inclusive_name',
                'inclusive_quantity',
                'inclusive_rate',
                'inclusive_start_date',
                'inclusive_end_date',
            ]);
        });
    }
};
